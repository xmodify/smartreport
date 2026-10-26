/**
 * Helper to ensure links work both under sub-path (/smartreport, /bms-homepage, /bms_homepage) and root (/)
 */
export function appPath(path = '') {
  let base = '';
  if (typeof window !== 'undefined' && window.location) {
    const pathname = window.location.pathname;
    const knownPrefixes = ['/smartreport', '/bms_homepage'];
    for (const prefix of knownPrefixes) {
      if (pathname.startsWith(prefix)) {
        base = prefix;
        break;
      }
    }
  }
  
  if (!path) return base || '/';
  
  const cleanPath = path.startsWith('/') ? path : '/' + path;
  
  // If path already starts with base, don't duplicate it
  if (base && cleanPath.startsWith(base)) {
    return cleanPath;
  }
  
  return `${base}${cleanPath}`;
}

