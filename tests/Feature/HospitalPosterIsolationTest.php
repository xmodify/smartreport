<?php

namespace Tests\Feature;

use App\Models\Homepage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HospitalPosterIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->withSession(['homepage_admin' => ['officer_id' => 1, 'name' => 'Admin']]);
    }

    /**
     * Test posters are isolated by hospital code (opdconfig.hospitalcode).
     */
    public function test_homepage_only_displays_posters_matching_current_hospital(): void
    {
        // Hospital A (10989)
        $posterA1 = Homepage::create([
            'hcode' => '10989',
            'title' => 'Poster Hospital A 1',
            'image' => 'sample1.jpg',
            'is_active' => true,
            'order_num' => 1,
        ]);
        $posterA2 = Homepage::create([
            'hcode' => '10989',
            'title' => 'Poster Hospital A 2',
            'image' => 'sample2.jpg',
            'is_active' => true,
            'order_num' => 2,
        ]);

        // Hospital B (10740)
        $posterB1 = Homepage::create([
            'hcode' => '10740',
            'title' => 'Poster Hospital B 1',
            'image' => 'sample_b1.jpg',
            'is_active' => true,
            'order_num' => 1,
        ]);

        // Visit BMS Homepage as Hospital A
        $responseA = $this->get('/bms-homepage?hcode=10989');
        $responseA->assertStatus(200);
        $responseA->assertInertia(fn ($page) => $page
            ->component('BmsHomepage/Index')
            ->where('hospitalCode', '10989')
            ->has('posters', 2)
            ->where('posters.0.title', 'Poster Hospital A 1')
            ->where('posters.1.title', 'Poster Hospital A 2')
        );

        // Visit BMS Homepage as Hospital B
        $responseB = $this->get('/bms-homepage?hcode=10740');
        $responseB->assertStatus(200);
        $responseB->assertInertia(fn ($page) => $page
            ->component('BmsHomepage/Index')
            ->where('hospitalCode', '10740')
            ->has('posters', 1)
            ->where('posters.0.title', 'Poster Hospital B 1')
        );
    }

    /**
     * Test HOSxP admin gallery in BMS Homepage displays isolated posters and stats.
     */
    public function test_admin_mode_only_displays_posters_for_active_hospital(): void
    {
        // 3 posters for Hospital 10989
        Homepage::create(['hcode' => '10989', 'title' => 'A1', 'image' => 'a1.jpg', 'is_active' => true]);
        Homepage::create(['hcode' => '10989', 'title' => 'A2', 'image' => 'a2.jpg', 'is_active' => true]);
        Homepage::create(['hcode' => '10989', 'title' => 'A3', 'image' => 'a3.jpg', 'is_active' => false]);

        // 2 posters for Hospital 99999
        Homepage::create(['hcode' => '99999', 'title' => 'X1', 'image' => 'x1.jpg', 'is_active' => true]);
        Homepage::create(['hcode' => '99999', 'title' => 'X2', 'image' => 'x2.jpg', 'is_active' => true]);

        // Access Hospital 10989 with admin flag
        $response = $this->get('/bms-homepage?hcode=10989&admin=1');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('BmsHomepage/Index')
            ->where('hospitalCode', '10989')
            ->where('canManageAdmin', true)
            ->has('adminPosters', 3)
            ->where('adminStats.total', 3)
            ->where('adminStats.active', 2)
            ->where('adminStats.inactive', 1)
            ->where('adminStats.max', 8)
            ->where('adminStats.remaining', 5)
        );
    }

    /**
     * Test uploaded posters store the hospital code automatically.
     */
    public function test_uploaded_posters_store_hcode(): void
    {
        $file = UploadedFile::fake()->image('hospital_poster.jpg', 800, 1200);

        $response = $this->post('/bms-homepage/posters?hcode=10740', [
            'image' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('homepage', [
            'hcode' => '10740',
            'is_active' => 1,
        ]);
    }

    /**
     * Test hospital cannot exceed the 8 posters limit.
     */
    public function test_hospital_cannot_exceed_8_posters_limit(): void
    {
        // Create 8 posters for Hospital 10989 (Quota full)
        for ($i = 1; $i <= 8; $i++) {
            Homepage::create([
                'hcode' => '10989',
                'title' => "Poster {$i}",
                'image' => "poster_{$i}.jpg",
                'is_active' => true,
                'order_num' => $i,
            ]);
        }

        $this->assertEquals(8, Homepage::forHospital('10989')->count());

        // Attempt to upload 9th poster for Hospital 10989
        $file = UploadedFile::fake()->image('poster_9.jpg', 800, 1200);
        $response = $this->post('/bms-homepage/posters?hcode=10989', [
            'image' => $file,
        ]);

        $response->assertSessionHasErrors(['image']);
        $this->assertEquals(8, Homepage::forHospital('10989')->count());

        // However, a DIFFERENT hospital (10740) CAN still upload up to 8 posters
        $responseOtherHosp = $this->post('/bms-homepage/posters?hcode=10740', [
            'image' => $file,
        ]);

        $responseOtherHosp->assertSessionHasNoErrors();
        $this->assertEquals(1, Homepage::forHospital('10740')->count());
    }

    /**
     * Test batch upload rejects if total exceeds 8 posters.
     */
    public function test_batch_upload_rejects_when_exceeding_8_posters(): void
    {
        // Create 6 existing posters
        for ($i = 1; $i <= 6; $i++) {
            Homepage::create([
                'hcode' => '10989',
                'title' => "Poster {$i}",
                'image' => "poster_{$i}.jpg",
                'is_active' => true,
            ]);
        }

        // Try to upload 3 more posters (6 + 3 = 9 > 8 limit)
        $files = [
            UploadedFile::fake()->image('b1.jpg', 800, 1200),
            UploadedFile::fake()->image('b2.jpg', 800, 1200),
            UploadedFile::fake()->image('b3.jpg', 800, 1200),
        ];

        $response = $this->post('/bms-homepage/posters?hcode=10989', [
            'images' => $files,
        ]);

        $response->assertSessionHasErrors(['image']);
        $this->assertEquals(6, Homepage::forHospital('10989')->count());

        // Uploading exactly 2 more posters should succeed (6 + 2 = 8 limit)
        $allowedFiles = [
            UploadedFile::fake()->image('ok1.jpg', 800, 1200),
            UploadedFile::fake()->image('ok2.jpg', 800, 1200),
        ];

        $responseSuccess = $this->post('/bms-homepage/posters?hcode=10989', [
            'images' => $allowedFiles,
        ]);

        $responseSuccess->assertSessionHasNoErrors();
        $this->assertEquals(8, Homepage::forHospital('10989')->count());
    }

    /**
     * Test hospital cannot mutate another hospital's poster (Cross-tenant security).
     */
    public function test_hospital_cannot_mutate_another_hospitals_poster(): void
    {
        $posterB = Homepage::create([
            'hcode' => '10740',
            'title' => 'Hospital B Poster',
            'image' => 'b.jpg',
            'is_active' => true,
        ]);

        // Hospital A tries to delete Hospital B's poster
        $response = $this->delete("/bms-homepage/posters/{$posterB->id}?hcode=10989");
        $response->assertStatus(404);

        // Hospital A tries to toggle Hospital B's poster
        $responseToggle = $this->post("/bms-homepage/posters/{$posterB->id}/toggle-active?hcode=10989");
        $responseToggle->assertStatus(404);

        // Poster should still exist unchanged
        $this->assertDatabaseHas('homepage', [
            'id' => $posterB->id,
            'hcode' => '10740',
        ]);
    }

    /**
     * Test hospital admin can delete a poster and its physical image file.
     */
    public function test_hospital_admin_can_delete_poster_and_physical_file(): void
    {
        $file = UploadedFile::fake()->image('test_delete.jpg', 600, 800);
        $path = Storage::disk('uploads')->putFileAs('homepage', $file, 'test_delete_10989.jpg');
        Storage::disk('uploads')->assertExists($path);

        $poster = Homepage::create([
            'hcode' => '10989',
            'title' => 'Poster To Delete',
            'image' => $path,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('homepage', ['id' => $poster->id]);

        $response = $this->delete("/bms-homepage/posters/{$poster->id}?hcode=10989");
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('homepage', ['id' => $poster->id]);
        Storage::disk('uploads')->assertMissing($path);
    }

    /**
     * Test hospital admin can edit poster details and replace image.
     */
    public function test_hospital_admin_can_edit_poster_details_and_replace_image(): void
    {
        $oldFile = UploadedFile::fake()->image('old.jpg', 600, 800);
        $oldPath = Storage::disk('uploads')->putFileAs('homepage', $oldFile, 'old_10989.jpg');
        Storage::disk('uploads')->assertExists($oldPath);

        $poster = Homepage::create([
            'hcode' => '10989',
            'title' => 'Original Title',
            'order_num' => 1,
            'image' => $oldPath,
            'is_active' => true,
        ]);

        $newFile = UploadedFile::fake()->image('new.jpg', 600, 800);

        $response = $this->post("/bms-homepage/posters/{$poster->id}?hcode=10989", [
            'title' => 'Updated Poster Title',
            'order_num' => 5,
            'is_active' => 0,
            'image' => $newFile,
        ]);

        $response->assertSessionHas('success');

        $poster->refresh();
        $this->assertEquals('Updated Poster Title', $poster->title);
        $this->assertEquals(5, $poster->order_num);
        $this->assertFalse((bool) $poster->is_active);
        $this->assertNotEquals($oldPath, $poster->image);

        // Old file deleted, new file exists
        Storage::disk('uploads')->assertMissing($oldPath);
        Storage::disk('uploads')->assertExists($poster->image);
    }

    /**
     * Test homepage provides queue heatmap and realtime data in Inertia props.
     */
    public function test_homepage_provides_queue_heatmap_in_props(): void
    {
        $response = $this->get('/bms-homepage?hcode=10989');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('BmsHomepage/Index')
            ->has('realtimeData')
            ->has('realtimeData.departments')
            ->has('queueHeatmap')
            ->has('queueHeatmap.hours')
            ->has('queueHeatmap.departments')
        );
    }

    /**
     * Test queue heatmap API returns formatted JSON with realtime and heatmap.
     */
    public function test_queue_heatmap_api_returns_json_for_today_and_7days(): void
    {
        $response = $this->getJson('/bms-homepage/queue-heatmap?date=2026-10-26');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'realtime' => [
                    'total_waiting',
                    'departments',
                    'is_live',
                    'sql',
                ],
                'heatmap' => [
                    'hours',
                    'departments',
                    'total_queues',
                    'peak_hour',
                    'peak_hour_count',
                    'selected_date',
                    'sql',
                ],
            ]);
    }

    /**
     * Test clinic service workload stats API returns formatted JSON by date.
     */
    public function test_clinic_stats_api_returns_json_by_date(): void
    {
        $this->fakeHosxpDatabase(fn (string $sql): array => str_contains($sql, 'COUNT(*) AS total')
            ? [['total' => 234]]
            : [
                ['name' => 'คลินิกผู้ป่วยนอกทั่วไป (OPD)', 'count' => 148],
                ['name' => 'คลินิกโรคเรื้อรัง (NCDs)', 'count' => 86],
            ]);

        $response = $this->getJson('/bms-homepage/clinic-stats?date=2026-10-22');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'selected_date',
                'thai_date',
                'is_live',
                'total_patients',
                'departments' => [
                    '*' => [
                        'name',
                        'count',
                        'percent',
                        'share',
                    ],
                ],
            ]);

        $this->assertEquals('2026-10-22', $response->json('selected_date'));
        $this->assertTrue($response->json('is_live'));
    }

    /**
     * Test hospital identity and ward census come directly from the HOSxP database.
     */
    public function test_homepage_reads_hospital_and_ward_census_from_hosxp_database(): void
    {
        $this->fakeHosxpDatabase(function (string $sql): array {
            return match (true) {
                str_contains($sql, 'FROM opdconfig') => [['hospitalcode' => '10989']],
                str_contains($sql, 'FROM hospcode') => [['name' => 'โรงพยาบาลหัวตะพาน']],
                str_contains($sql, 'FROM ward w') => [
                    ['ward' => '01', 'name' => 'ผู้ป่วยใน สามัญ', 'total' => 25],
                    ['ward' => '03', 'name' => 'ผู้ป่วยใน VIP', 'total' => 12],
                    ['ward' => '10', 'name' => 'ผู้ป่วยหนัก', 'total' => 3],
                ],
                default => [],
            };
        });

        $this->get('/bms-homepage')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dbConnected', true)
                ->where('hospitalCode', '10989')
                ->where('hospitalName', 'โรงพยาบาลหัวตะพาน')
                ->where('wardCensus.0.ward', '03')
                ->where('wardCensus.0.name', 'ผู้ป่วยใน VIP')
                ->where('wardCensus.0.count', 12)
                ->where('wardCensus.1.count', 25)
                ->where('wardCensus.2.count', 3)
            );
    }

    /**
     * Test homepage still renders and reports the failure when HOSxP database is unreachable.
     */
    public function test_homepage_reports_error_when_hosxp_database_is_unreachable(): void
    {
        $this->get('/bms-homepage?hcode=10989')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dbConnected', false)
                ->where('dbError', 'ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP')
                ->where('clinicStats.is_live', false)
                ->has('wardCensus', 3)
            );
    }

    /**
     * Test visitors who did not sign in as HOSxP administrator cannot manage posters.
     */
    public function test_visitor_without_admin_sign_in_cannot_manage_posters(): void
    {
        $this->flushSession();

        $this->get('/bms-homepage?hcode=10989')
            ->assertInertia(fn ($page) => $page->where('canManageAdmin', false)->where('adminPosters', []));

        $this->post('/bms-homepage/posters?hcode=10989', [
            'image' => UploadedFile::fake()->image('p.jpg', 800, 1200),
        ])->assertForbidden();

        $this->assertEquals(0, Homepage::count());
    }

    /**
     * Test an active member of the HOSxP "ผู้ดูแลระบบ" group can sign in with the HOSxP password.
     */
    public function test_hosxp_administrator_can_sign_in(): void
    {
        $this->flushSession();
        $this->fakeHosxpDatabase(fn (string $sql): array => str_contains($sql, 'FROM officer o')
            ? [['officer_id' => 104, 'officer_name' => 'นายทดสอบ ระบบ', 'officer_login_password_md5' => strtoupper(md5('secret-pass'))]]
            : []);

        $this->post('/bms-homepage/admin/login', ['login_name' => '1341800000000', 'password' => 'secret-pass'])
            ->assertRedirect('/bms-homepage');

        $this->get('/bms-homepage?hcode=10989')
            ->assertInertia(fn ($page) => $page->where('canManageAdmin', true)->where('adminName', 'นายทดสอบ ระบบ'));

        $this->post('/bms-homepage/admin/logout')->assertRedirect('/bms-homepage');

        $this->get('/bms-homepage?hcode=10989')
            ->assertInertia(fn ($page) => $page->where('canManageAdmin', false));
    }

    /**
     * Test a wrong password is rejected.
     */
    public function test_sign_in_is_rejected_for_wrong_password(): void
    {
        $this->flushSession();
        $this->fakeHosxpDatabase(fn (): array => [['officer_id' => 104, 'officer_name' => 'A', 'officer_login_password_md5' => md5('right')]]);

        $this->post('/bms-homepage/admin/login', ['login_name' => 'admin', 'password' => 'wrong'])
            ->assertSessionHasErrors('login_name');

        $this->get('/bms-homepage?hcode=10989')
            ->assertInertia(fn ($page) => $page->where('canManageAdmin', false));
    }

    /**
     * Test officers outside the administrator group are rejected (query returns no rows).
     */
    public function test_sign_in_is_rejected_for_non_administrator(): void
    {
        $this->flushSession();
        $this->fakeHosxpDatabase(fn (): array => []);

        $this->post('/bms-homepage/admin/login', ['login_name' => 'nurse', 'password' => 'right'])
            ->assertSessionHasErrors('login_name');
    }
}
