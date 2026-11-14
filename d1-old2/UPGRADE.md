# Upgrade: project galleries and SEO administration

Back up the existing database, `config.php` and `storage/photos` before upgrading. Keep the backup outside the public document root.

1. Replace application/source files from the updated ZIP. Preserve your existing private `config.php` and the entire `storage` directory.
2. Run from the project directory using a database account with migration privileges:

   ```powershell
   php bin/upgrade.php
   ```

   This creates `project_photos` and `seo_settings`, and copies each legacy project cover into its gallery. It does not delete projects or change their cover images. Re-running it does not duplicate photos. Fresh installations can continue to use `php bin/install.php`.
3. Use the restricted application database account for normal traffic after migration. Ensure it has access to the new tables.
4. In PHP/hosting settings use `upload_max_filesize=5M`, `post_max_size=64M`, `max_file_uploads=20`, and `memory_limit=256M`. Set the reverse proxy/web-server body limit to at least 64 MB. PHP's default small POST limit prevents selecting several large photos. Each decoded image is still limited to 5 MB / 20 megapixels; a project may contain 1–12 images.
5. Admin → Project gallery → Edit. Add several files with the multi-select file picker. Existing photos remain. Select a cover, edit each photo's descriptive alt text, or mark individual photos for removal and save. To use a newly uploaded image as cover, save the upload first, then select its cover control and save again. Removing the current cover automatically chooses a remaining image. Projects must keep at least one image.
6. Visitors open a project from the homepage to see its complete gallery. Draft project pages and their photos are unavailable publicly.
7. Admin → SEO settings. Configure homepage and privacy titles/descriptions, the canonical website root URL, social title/description/image, the Google Search Console verification token and indexing preference. Project pages derive metadata from their editable title, description and cover photo. Social sharing metadata does not publish to social networks.

The canonical URL must be the actual deployed website root (HTTPS in production), not a preview URL. Leave it blank only when private `base_url` is correct. Search verification accepts the token only; never paste a full script or meta tag.

## Sitemap and robots routing

- `/sitemap.php` is a valid XML sitemap URL and can be submitted directly in Google Search Console.
- `/robots.php` generates crawler guidance. Apache's included `public/.htaccess` maps `/robots.txt` to it and `/sitemap.xml` to the sitemap when `mod_rewrite` and overrides are enabled.
- With Nginx, add exact rewrites in the site's server configuration, using the existing PHP-FPM handler:

  ```nginx
  location = /robots.txt { rewrite ^ /robots.php last; }
  location = /sitemap.xml { rewrite ^ /sitemap.php last; }
  ```

- The PHP development server does not apply `.htaccess`; inspect the `.php` endpoints locally. Test the aliases on the real Apache/Nginx host.
- Turning indexing off writes `noindex,nofollow` into public HTML and empties the sitemap. Crawling remains allowed so compliant crawlers can read noindex. Admin is always noindex. These rules do not make a site private and do not guarantee immediate removal from search.

Removed gallery and social images stop being served publicly as soon as their last public reference is removed. The daily maintenance command deletes unreferenced physical files after 24 hours. It preserves all gallery and active social images.

Official SEO references: [Google robots directives](https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag), [canonical URLs](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls), [sitemaps](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).
