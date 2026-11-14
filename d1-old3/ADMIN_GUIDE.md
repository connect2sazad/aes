# Aanshu Engineering — Admin guide

Also available inside Admin → Help & docs, with search and print controls.


## 1. First login and the overview

Open this installation’s ">admin login. Use your own administrator email and password. Overview counts show saved records, including drafts. “View website” opens the public site in another tab.

Use the navigation to edit content, projects, reviews, posts, enquiries, SEO, social accounts and security. Save buttons apply changes immediately. Draft records stay private until published.

## 2. Edit business content and contact details

- Open Page content.
- Change the homepage headline, service descriptions, company profile, scheme text or registered details.
- Use digits and country code for the phone, for example 919114959111.
- Add the confirmed business email to show an email link to visitors.
- Save website content, then open View website to check it.

Use plain text. HTML and scripts are escaped. Update business identifiers only after verifying the correct details. Layout and supporting labels remain source-managed.

## 3. Add a project and multiple photos

- Open Project gallery. Enter title, Solar/Electrical category, location and description.
- Choose several photos together in the file picker (Ctrl/Shift on desktop). A project can have 1–12 photos, each up to 5 MB and 20 megapixels. JPG, PNG and WebP are accepted and converted to JPEG.
- Tick Publish on website to make the project visible, or save it as a draft.
- To add more photos later, click Edit. Existing photos stay unless marked for removal.
- Select one Cover photo. New photos become selectable after their first save.
- Write descriptive Alt text for each photo. Describe what is visible; do not stuff it with keywords.
- Mark Remove when saved to remove individual images. A project must keep at least one photo.
- Save and open the project on the public website to check the full gallery.

If any photo in a batch is invalid, the new batch is rejected without saving part of it. Re-select files after fixing an error. Ask the host to set PHP post_max_size to 64M and upload_max_filesize to 5M if large batches fail.

## 4. Add customer reviews responsibly

Open Customer reviews. Enter the real customer name, location, review and star rating. Confirm permission to publish. Tick Publish on website and save. Edit or delete it later from the saved list. Never invent testimonials or publish private customer details without permission.

## 5. Website posts and social captions

Open Posts & social. Add a title, body and optional photo. Tick Publish on website, then save. This publishes to the website only. Copy caption supports manual sharing. For automatic Facebook/Instagram publishing, go to Social accounts and queue the saved post for each network. The caption includes the title followed by the body.

Editing or deleting a website post does not edit or delete a post already on a social network. Manage those changes on the network. Unpublishing or deleting the website post cancels jobs that have not started; jobs already submitted may still complete.

## 6. Respond to enquiries and email

Open Enquiries to see names, phone numbers, email addresses and project messages. Call or email the customer, then set the status to contacted or closed. Delete personal data when it is no longer required. The maintenance task deletes closed enquiries older than 180 days; review old active enquiries manually.

WhatsApp buttons open a conversation; the visitor sends the message. Email links open the visitor’s email application. Website form enquiries always save to the admin inbox first. Notification email is separate: your host must configure mail delivery and the fixed recipient in private config.php. “Accepted by mail server” does not prove inbox delivery.

## 7. SEO and Google Search Console

- Open SEO settings.
- Write a clear homepage search title and meta description. Privacy has separate metadata.
- Enter the full public website URL, including any subfolder, for example https://your-domain.in/aes/d1/public/. On localhost use your actual local URL while testing and replace it before launch.
- Optionally add social sharing text and an approved landscape image. These control link previews; they do not publish posts.
- Paste only the content token from Google’s verification meta tag.
- Allow indexing for the live public site; turn it off for a staging site. Noindex does not make a website private.
- Save and inspect the generated XML sitemap. Submit its live URL in Google Search Console.

Project metadata comes from its title, description and cover photo. Search engines control rankings and when updated information appears.

## 8. Social profiles and automatic publishing setup

Public links: Open Social accounts and paste the company’s HTTPS Facebook, Instagram, LinkedIn, YouTube and X profile URLs. Leave unused networks blank. Save to update the footer.

Developer setup for automatic publishing: Use a Meta developer app and authorized Page access token. Facebook publishing targets a Facebook Page, not a personal timeline. This integration uses Instagram with Facebook Login: the Instagram professional account must be linked to the relevant Page. The developer must configure the app/login/access level and obtain the appropriate permissions. Account IDs are numeric IDs, not usernames.

Facebook needs the relevant Page publishing permissions, including pages_manage_posts; Instagram needs instagram_basic, instagram_content_publish and pages_read_engagement for this login flow. Additional review, business verification, Page tasks or permissions may apply to the client’s account. Check the current official Meta requirements. Do not use your Facebook password as a token.

- Enter the numeric Page ID and token under Facebook, then enable it.
- Enter the Instagram professional account ID and authorized token under Instagram, then enable it.
- Use a real public HTTPS website URL in SEO settings. Meta cannot download a localhost photo.
- Ask the host to schedule php bin/publish-social.php every minute.
- Save a published website post. Queue it once per required network.
- Check Publishing history for each result and verify the first real posts on both accounts.

Instagram requires a JPEG photo with aspect ratio between 4:5 and 1.91:1 and a total title/caption no longer than 2,200 characters. This release publishes one photo per social post; project galleries can still have multiple website photos. Video/Reels, social carousels and direct LinkedIn/YouTube/X publishing are not implemented.

Tokens are encrypted using the private app_key. Keep that key backed up and secret; changing it requires re-entering account tokens. Keep tokens out of screenshots, ZIP files and support messages. Saving a token does not verify it with Meta. Rotate expired tokens through the authorized Meta workflow and update the token field.

Official Facebook Page publishing docs ↗ · Official Instagram publishing docs ↗

## 9. Understand publishing status and retries

- Pending: waiting for the scheduled worker or Instagram processing. Check that the worker is scheduled.
- Processing: the worker is talking to Meta. Do not create another job.
- Sent: Meta returned a publication ID, or an administrator explicitly verified publication.
- Failed: rejected or invalid configuration. Read the message, fix the cause and check the real account before retrying.
- Needs review: the outcome is uncertain, for example a timeout after submission. Check the real account first. Mark it as manually verified if already published; retry only after confirming it did not publish.
- Cancelled: stopped before sending, for example the account was disabled or the website post unpublished.

The worker does not automatically repeat uncertain writes. One network succeeding does not mean the other succeeded. Already-sent jobs cannot be queued again for the same website post/network.

## 10. Passwords, sessions and team access

Use Security to change your password with the current password. Use a unique password of at least 14 characters. Other sessions are invalidated after a password change. Sessions expire after 30 minutes idle or 8 hours total. Sign out on shared devices.

Forgotten passwords are reset by the server administrator with php bin/admin.php owner@example.com. There is no default production password, email password-reset flow, MFA or role-based team access in this version. Do not share an administrator password with customers.

## 11. Troubleshooting links, errors and uploads

Subfolder links: browser links are generated from the installation path automatically. There is no need to replace every slash with a dot. For unusual reverse proxies, set private config base_path to the public path (for example /aes/d1/public), without the domain. Keep PHP require/include filesystem paths unchanged.

SEO service unavailable: the previous copied code had a broken filesystem include. This version uses __DIR__ for that include. If a new error appears, check the server’s protected PHP error log and Installation checks. Do not expose logs or config files publicly.

Database update required: back up your database and photos; run php bin/upgrade.php from the project folder. Normal traffic uses a restricted database user; migrations need permission to create tables.

HTTPS required locally: use a properly configured local HTTPS environment or set production=false only for local development. Production must retain HTTPS enforcement. Do not weaken a production certificate or token check.

Form expired: refresh the page and sign in again if necessary. PHP upload/request limits can also discard an oversized form. Re-select photos after refreshing.

Social job stuck: check the worker scheduler, cURL extension, public image URL, account permissions and token expiry. Inspect each network’s job result. A social crawler must be able to fetch the image without signing in.

## 12. Launch and maintenance checklist

- Confirm company details, scheme/vendor claims, phone ownership and contact email.
- Use only approved installation photos and genuine customer reviews.
- Set the correct live canonical URL and indexing preference.
- Keep config, sessions, uploads and archives outside public access. Prefer a document root pointing only at public/.
- Test an enquiry, login, gallery, social link, email delivery and first real social publication on the deployed site.
- Back up the database, app_key and photos, and test restoring them.
- Schedule php bin/maintenance.php daily and the social worker every minute if publishing is enabled.
- Keep the PHP, database, web server and operating system patched.



Developer setup and exact upgrade commands are in UPGRADE.md.
