# Request contract

All endpoints are same-origin. No permissive CORS. Responses are server-rendered HTML with 303 Post/Redirect/Get after successful writes. Public text is escaped, never interpreted as HTML. Mutations use POST with a session-bound `csrf` form field. Invalid CSRF returns 403; rate limits return 429 with Retry-After; infrastructure failures return a generic 503.

| Endpoint | Access | Behavior |
| --- | --- | --- |
| GET `/` | Public | Published content, projects, posts, reviews; new session/CSRF field |
| POST `/` | Public + CSRF | `name` (100), `phone` (30), optional `email` (190), service allowlist, `message` (3000), `consent=yes`, empty `website` honeypot. Persists enquiry, optional fixed-recipient mail notification. 5 attempts/IP/hour. Validation errors render feedback. |
| GET `/privacy.php` | Public | Privacy notice |
| GET `/media.php?file=...` | Public for published image; otherwise admin | Only random 48-hex JPEG filename. Returns JPEG or 404; no filesystem paths accepted. |
| GET `/admin.php` | Public login / authenticated admin | Overview or login; no-store |
| POST `/admin.php`, `action=login` | CSRF | Email/password, max 8 attempts/IP/15 minutes; generic failed credential message. Successful login rotates session ID and CSRF. |
| POST `/admin.php`, `action=logout` | Admin + CSRF | Destroy session |
| POST `/admin.php?tab=content`, `action=content` | Admin + CSRF | Existing content keys only, contact validation, atomic update |
| POST `/admin.php?tab=projects`, `action=save` | Admin + CSRF | Create or edit by `id` (0=create), title/category/location/description, optional replacement photo; photo required at creation, allowlisted category Solar/Electrical. `published` checkbox controls visibility. |
| POST `/admin.php?tab=reviews`, `action=save` | Admin + CSRF | Customer/location/quote/rating 1–5, permission confirmation, optional publication |
| POST `/admin.php?tab=posts`, `action=save` | Admin + CSRF | Title/body/optional photo/publication; website only |
| POST entity tab, `action=delete` | Admin + CSRF | Delete by numeric `id`; permanent; orphaned photo removed by maintenance |
| POST `/admin.php?tab=enquiries`, `action=status` | Admin + CSRF | `id`, status new/contacted/closed |
| POST `/admin.php?tab=security`, `action=password` | Admin + CSRF | Current password plus new password 14–200 characters; invalidates other sessions |

Uploads: JPEG/PNG/WebP only, actual decoded type checked, <=5 MB and <=20 megapixels, normalized to JPEG <=1800 px wide. Stored outside document root with random names; draft media is not public. PHP/server size limits also apply.
