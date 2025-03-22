# Admin actions?

- move-move can be an admin action:
	- https://pl.wikipedia.org/w/index.php?title=Specjalna:Rejestr&logid=51154750&uselang=en
	- move page $X to $Y without leaving a redirect
	- right: suppressredirect
- delte-delete_redir is not an admin action, can be done by editors:
	- https://pl.wikipedia.org/w/index.php?title=Specjalna:Rejestr&logid=51154847&uselang=en
	- deleted redirect $X by overwriting

## AdminStats

Admin stats allows choosing stats, but has na option to just show all.

https://xtools.wmcloud.org/adminstats/pl.wikipedia.org/2024-03-23/2025-03-22?actions=delete
	|revision-delete
	|log-delete
	|restore
	|re-block
	|unblock
	|re-protect
	|unprotect
	|rights
	|merge
	|import
	|abusefilter
	|contentmodel

Defaults:
delete, revision delete, log delete, restore, (re)block, unblock, (re)protect, unprotect, rights, merge, import, abusefilter, content model

## Stewardry

https://github.com/Pathoschild/Wikimedia-contrib/blob/main/tool-labs/stewardry/framework/StewardryEngine.php

'sysop' => ['abusefilter', 'block', 'delete', 'protect', 'rights'],
$user["last_$groupName"] = $this->db->query('SELECT log_timestamp FROM logging_userindex WHERE log_actor = ? AND log_type IN (\'' . implode('\',\'', $rights[$groupName]) . '\') ORDER BY log_id DESC LIMIT 1', [$user['actor_id']])->fetchValue();

## List of admin rights

https://pl.wikipedia.org/wiki/Specjalna:Grupy_u%C5%BCytkownik%C3%B3w?uselang=en#sysop

### Shared with other, ~common  groups
Bypass IP blocks, auto-blocks and range blocks (ipblock-exempt)
Create new user accounts (createaccount)
Create short URLs (urlshortener-create-url)
Edit title of Structured Discussions topics by other users (flow-edit-title)
Edit Structured Discussions posts by other users (flow-edit-post)
Edit pages protected as "Allow only autoconfirmed users" (editsemiprotected)
Not be affected by IP-based rate limits (autoconfirmed)
Perform CAPTCHA-triggering actions without having to go through the CAPTCHA (skipcaptcha)
Reset failed or transcoded videos so they are inserted into the job queue again (transcode-reset)
Upload files (upload)
View information about the current transcode activity (transcode-status)
Overwrite existing files (reupload)
Move pages (move)
Edycja stron zabezpieczonych na poziomie średnim (editor)
Have one's own edits automatically marked as "checked" (autoreview)
Mark Structured Discussions topics as resolved (flow-lock)
Mark revisions as being "checked" (review)
Quickly rollback the edits of the last user who edited a particular page (rollback)
View the list of unreviewed pages (unreviewedpages)

### Admin (or checkusers etc)
Access a full view of the IP information attached to revisions or log entries (ipinfo-view-full)
Block or unblock a user from sending email (blockemail)
Block or unblock other users from editing (block)
Change protection settings and edit cascade-protected pages (protect)
Create and (de)activate tags (managechangetags)
Create or modify what external domains are blocked from being linked (abusefilter-modify-blocked-external-domains)
Delete tags from the database (deletechangetags)
Delete Structured Discussions topics and posts (flow-delete)
Delete and undelete specific log entries (deletelogentry)
Delete and undelete specific revisions of pages (deleterevision)
Delete event registrations (campaignevents-delete-registration)
Delete pages (delete)
Disable global blocks locally (globalblock-whitelist)
Edit other users' JSON files (edituserjson)
Edit pages protected as "Allow only administrators" (editprotected)
Edit sitewide JSON (editsitejson)
Edit the content model of a page (editcontentmodel)
Edit the user interface (editinterface)
Enable two-factor authentication (oathauth-enable)
Forcibly create a local account for a global account (centralauth-createlocal)
Have one's own edits automatically marked as patrolled (autopatrol)
Import pages from other wikis (import)
Manage the list of mentors (managementors)
Mark others' edits as patrolled (patrol)
Mark rolled-back edits as bot edits (markbotedits)
Mass delete pages (nuke)
Merge the history of pages (mergehistory)
Modify abuse filters with restricted actions (abusefilter-modify-restricted)
Move category pages (move-categorypages)
Move files (movefile)
Move pages with stable versions (movestable)
Move pages with their subpages (move-subpages)
Move root user pages (move-rootuserpages)
Not be affected by rate limits (noratelimit)
Not create redirects from source pages when moving pages (suppressredirect)
Override files on the shared media repository locally (reupload-shared)
Override the disallowed titles or usernames list (tboverride)
Override the spoofing checks (override-antispoof)
Search deleted pages (browsearchive)
Send a message to multiple users at once (massmessage)
Set user's mentor (setmentor)
Undelete a page (undelete)
Use higher limits in API queries (apihighlimits)
View IP addresses used by temporary accounts (checkuser-temporary-account)
View a list of unwatched pages (unwatchedpages)
View and create filters that use protected variables (abusefilter-access-protected-vars)
View deleted history entries, without their associated text (deletedhistory)
View deleted text and changes between deleted revisions (deletedtext)
View detailed abuse log entries (abusefilter-log-detail)
View log entries of abuse filters marked as private (abusefilter-log-private)
View the disallowed titles list log (titleblacklistlog)
Add groups: IP block exemptions, Editors, Autochecked users and Event organizers
Remove groups: IP block exemptions, Editors, Autochecked users and Event organizers
Add group to own account: Pseudobots
