<?php

namespace Tests\Feature;

use App\Models\BmsSession;
use App\Services\Bms\Bms;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BmsSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test system always selects the session with the LONGEST REMAINING TIME.
     */
    public function test_system_selects_session_with_longest_remaining_time(): void
    {
        $deviceKey = 'workstation_opd_01';

        // Session 1: Expires in 1 hour
        $session1 = BmsSession::create([
            'device_key' => $deviceKey,
            'bms_session_id' => 'session-1hr',
            'bms_url' => 'https://tunnel1.hosxp.net',
            'bms_session_code' => 'token-1',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        // Session 2: Expires in 8 hours (Longest remaining)
        $session2 = BmsSession::create([
            'device_key' => $deviceKey,
            'bms_session_id' => 'session-8hr',
            'bms_url' => 'https://tunnel2.hosxp.net',
            'bms_session_code' => 'token-2',
            'expires_at' => Carbon::now()->addHours(8),
        ]);

        // Session 3: Expires in 2 hours
        $session3 = BmsSession::create([
            'device_key' => $deviceKey,
            'bms_session_id' => 'session-2hr',
            'bms_url' => 'https://tunnel3.hosxp.net',
            'bms_session_code' => 'token-3',
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        // Query active sessions ordered by longest remaining time
        $bestSession = BmsSession::forDevice($deviceKey)->longestRemaining()->first();

        $this->assertNotNull($bestSession);
        $this->assertEquals('session-8hr', $bestSession->bms_session_id);

        // Also test through Bms::current() helper
        $current = Bms::current($deviceKey);
        $this->assertNotNull($current);
        $this->assertEquals('session-8hr', $current->bms_session_id);
    }

    /**
     * Test expired sessions are ignored and pruned.
     */
    public function test_expired_sessions_are_ignored_and_pruned_by_command(): void
    {
        $deviceKey = 'workstation_er_01';

        // 1 Active session (expires in 3 hours)
        $activeSession = BmsSession::create([
            'device_key' => $deviceKey,
            'bms_session_id' => 'session-active',
            'bms_url' => 'https://active.tunnel.hosxp.net',
            'bms_session_code' => 'token-active',
            'expires_at' => Carbon::now()->addHours(3),
        ]);

        // 2 Expired sessions
        $expired1 = BmsSession::create([
            'device_key' => $deviceKey,
            'bms_session_id' => 'session-expired-1',
            'bms_url' => 'https://exp1.tunnel.hosxp.net',
            'bms_session_code' => 'token-expired-1',
            'expires_at' => Carbon::now()->subMinutes(10),
        ]);

        $expired2 = BmsSession::create([
            'device_key' => $deviceKey,
            'bms_session_id' => 'session-expired-2',
            'bms_url' => 'https://exp2.tunnel.hosxp.net',
            'bms_session_code' => 'token-expired-2',
            'expires_at' => Carbon::now()->subHours(2),
        ]);

        $this->assertEquals(3, BmsSession::count());

        // Run pruning command
        Artisan::call('bms:prune-sessions');

        // Only active session should remain
        $this->assertEquals(1, BmsSession::count());
        $this->assertDatabaseHas('bms_sessions', ['bms_session_id' => 'session-active']);
        $this->assertDatabaseMissing('bms_sessions', ['bms_session_id' => 'session-expired-1']);
        $this->assertDatabaseMissing('bms_sessions', ['bms_session_id' => 'session-expired-2']);
    }

    /**
     * Test Laravel native model:prune command works with BmsSession.
     */
    public function test_laravel_model_prune_command_deletes_expired_sessions(): void
    {
        BmsSession::create([
            'device_key' => 'dev_test',
            'bms_session_id' => 'session-prunable',
            'bms_url' => 'https://test.tunnel.hosxp.net',
            'bms_session_code' => 'token-test',
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $this->assertEquals(1, BmsSession::count());

        Artisan::call('model:prune', ['--model' => [BmsSession::class]]);

        $this->assertEquals(0, BmsSession::count());
    }

    /**
     * Test session code is encrypted in database.
     */
    public function test_session_code_is_encrypted_in_database(): void
    {
        $rawCode = 'secret-raw-bms-token-xyz-123';

        $session = BmsSession::create([
            'device_key' => 'dev_enc',
            'bms_session_id' => 'session-enc',
            'bms_url' => 'https://enc.tunnel.hosxp.net',
            'bms_session_code' => $rawCode,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        // When retrieved via model, it is decrypted
        $this->assertEquals($rawCode, $session->bms_session_code);

        // When queried raw directly from database, it is not the raw string
        $rawDb = DB::table('bms_sessions')
            ->where('id', $session->id)
            ->value('bms_session_code');

        $this->assertNotEquals($rawCode, $rawDb);
    }

    /**
     * Test isHosxpAdmin returns true when user is in active admin group via BMS Session /api/sql.
     */
    public function test_is_hosxp_admin_returns_true_when_user_is_active_admin(): void
    {
        Http::fake([
            'https://tunnel.hosxp.net/api/sql' => Http::response([
                'result' => [
                    [
                        'officer_id' => 104,
                        'officer_name' => 'นายชัยพร กาหาวงศ์',
                        'officer_login_name' => '1341800053431',
                        'officer_active' => 'Y',
                    ],
                ],
                'MessageCode' => 200,
                'Message' => 'OK',
            ], 200),
        ]);

        $session = BmsSession::create([
            'device_key' => 'dev_admin',
            'bms_session_id' => 'session-admin-user',
            'bms_url' => 'https://tunnel.hosxp.net',
            'bms_session_code' => 'valid-code',
            'user_id' => '1341800053431',
            'user_name' => 'นายชัยพร กาหาวงศ์',
            'expires_at' => Carbon::now()->addHours(5),
        ]);

        $this->assertTrue(Bms::data()->isHosxpAdmin($session));
    }

    /**
     * Test isHosxpAdmin returns false when user is inactive or not in admin group.
     */
    public function test_is_hosxp_admin_returns_false_when_not_in_group(): void
    {
        Http::fake([
            'https://tunnel.hosxp.net/api/sql' => Http::response([
                'result' => [],
                'MessageCode' => 200,
                'Message' => 'OK',
            ], 200),
        ]);

        $session = BmsSession::create([
            'device_key' => 'dev_user',
            'bms_session_id' => 'session-normal-user',
            'bms_url' => 'https://tunnel.hosxp.net',
            'bms_session_code' => 'valid-code',
            'user_id' => '3341601345692', // นายสายฝน จันทะบุตร (ปิดใช้งาน)
            'user_name' => 'นายสายฝน จันทะบุตร',
            'expires_at' => Carbon::now()->addHours(5),
        ]);

        $this->assertFalse(Bms::data()->isHosxpAdmin($session));
    }

    /**
     * Test formatParams formats parameters into official BMS 7-value_type schema.
     */
    public function test_format_params_converts_to_official_bms_schema(): void
    {
        $raw = [
            'hn' => '0001234',
            'age' => 45,
            'weight' => 68.5,
            'vstdate' => '2026-10-25',
            'vsttime' => '14:30:00',
            'datetime' => '2026-10-25T14:30:00',
        ];

        $formatted = Bms::data()->formatParams($raw);

        $this->assertEquals('string', $formatted['hn']['value_type']);
        $this->assertEquals('0001234', $formatted['hn']['value']);

        $this->assertEquals('integer', $formatted['age']['value_type']);
        $this->assertEquals(45, $formatted['age']['value']);

        $this->assertEquals('float', $formatted['weight']['value_type']);
        $this->assertEquals(68.5, $formatted['weight']['value']);

        $this->assertEquals('date', $formatted['vstdate']['value_type']);
        $this->assertEquals('2026-10-25', $formatted['vstdate']['value']);

        $this->assertEquals('time', $formatted['vsttime']['value_type']);
        $this->assertEquals('14:30:00', $formatted['vsttime']['value']);

        $this->assertEquals('datetime', $formatted['datetime']['value_type']);
        $this->assertEquals('2026-10-25T14:30:00', $formatted['datetime']['value']);
    }

    /**
     * Test executeSql throws exception when MessageCode is not 200 even if HTTP status is 200.
     */
    public function test_execute_sql_throws_on_non_200_message_code(): void
    {
        Http::fake([
            'https://tunnel.hosxp.net/api/sql' => Http::response([
                'result' => [],
                'MessageCode' => 409,
                'Message' => 'Database error: #42S22 Unknown column',
            ], 200),
        ]);

        $session = BmsSession::create([
            'device_key' => 'dev_test',
            'bms_session_id' => 'session-error-test',
            'bms_url' => 'https://tunnel.hosxp.net',
            'bms_session_code' => 'token-error',
            'expires_at' => Carbon::now()->addHours(1),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('BMS SQL Error [409]');

        Bms::data()->executeSql($session, 'SELECT unknown_col FROM patient');
    }

    /**
     * Test manual BMS Session connection via POST /bms-homepage/session/connect.
     */
    public function test_can_connect_session_manually(): void
    {
        Http::fake([
            '*' => Http::response([
                'MessageCode' => 200,
                'result' => [
                    'bms_url' => 'http://127.0.0.1:45011',
                    'bms_session_code' => 'TEST-SESSION-CODE',
                    'expired_second' => 36000,
                    'hospital_code' => '10989',
                    'hospital_name' => 'โรงพยาบาลทดสอบ',
                ],
            ], 200),
        ]);

        $response = $this->post('/bms-homepage/session/connect', [
            'bms_session_id' => 'MANUAL-TEST-SESSION-UUID-1234',
        ]);

        $response->assertRedirect('/bms-homepage');
        $this->assertDatabaseHas('bms_sessions', [
            'bms_session_id' => 'MANUAL-TEST-SESSION-UUID-1234',
            'hospital_code' => '10989',
        ]);
    }

    /**
     * Test manual BMS Session disconnection via POST /bms-homepage/session/disconnect.
     */
    public function test_can_disconnect_session_manually(): void
    {
        Http::fake([
            '*' => Http::response([
                'MessageCode' => 200,
                'result' => [
                    'bms_url' => 'http://127.0.0.1:45011',
                    'bms_session_code' => 'TEST-SESSION-CODE',
                    'expired_second' => 36000,
                    'hospital_code' => '10989',
                ],
            ], 200),
        ]);

        $headers = ['X-Device-Key' => 'test-device-workstation'];

        $this->post('/bms-homepage/session/connect', [
            'bms_session_id' => 'SESSION-TO-BE-DELETED',
        ], $headers);

        $this->assertDatabaseHas('bms_sessions', [
            'bms_session_id' => 'SESSION-TO-BE-DELETED',
            'device_key' => 'test-device-workstation',
        ]);

        $response = $this->post('/bms-homepage/session/disconnect', [], $headers);
        $response->assertRedirect('/bms-homepage');

        $this->assertDatabaseMissing('bms_sessions', [
            'bms_session_id' => 'SESSION-TO-BE-DELETED',
        ]);
    }

    /**
     * Test connect fails gracefully without falling back when gateway is unreachable.
     */
    public function test_connect_session_fails_gracefully_without_fallback(): void
    {
        Http::fake([
            '*' => Http::response(['MessageCode' => 500, 'Message' => 'Gateway down'], 500),
        ]);

        $response = $this->from('/bms-homepage')->post('/bms-homepage/session/connect', [
            'bms_session_id' => 'INVALID-OR-OFFLINE-SESSION',
        ]);

        $response->assertRedirect('/bms-homepage');
        $response->assertSessionHasErrors('bms_session_id');

        $this->assertDatabaseMissing('bms_sessions', [
            'bms_session_id' => 'INVALID-OR-OFFLINE-SESSION',
        ]);
    }
}
