# Admin actions?

- move-move can be an admin action:
	- https://pl.wikipedia.org/w/index.php?title=Specjalna:Rejestr&logid=51154750&uselang=en
	- move page $X to $Y without leaving a redirect
	- right: suppressredirect
	- https://gerrit.wikimedia.org/g/mediawiki/core/+/68ab52fb5ee2c29541dbfe2011d419c45f1e5857/includes/logging/MoveLogFormatter.php#121
- delte-delete_redir is not an admin action, can be done by editors:
	- https://pl.wikipedia.org/w/index.php?title=Specjalna:Rejestr&logid=51154847&uselang=en
	- deleted redirect $X by overwriting

## Actions

Columns:
- log_type IN ('delete', 'block', 'protect')
- log_action aka subtype

Standard (log_type: log_action):
- protect (all?): modify, move_prot, protect, unprotect

Other (most probably admin actions, but seem less common or less imporant):
- abusefilter: create, modify

logdelete, reprotect, rights, merge, import, abusefilter, contentmodel

Special (log_type - log_action):
- suppressredirect: move-move (maybe move-move_redir) + log_params LIKE '%s:10:"5::noredir";s:1:"1"%'
	- move-move suppression https://pl.wikipedia.org/w/index.php?title=Specjalna:Rejestr&logid=51154750&uselang=en
	- move-move no-suppression https://pl.wikipedia.org/w/index.php?title=Specjalna:Rejestr&logid=13263280&uselang=en
	- counting: https://quarry.wmcloud.org/query/91957

Based on:
https://quarry.wmcloud.org/query/91955

**Standard**:
^          log_type ^         log_action ^ notes                                                            ^
|            delete |                 ~* | log_action NOT IN ('delete_redir')
|            delete |             delete | .
|            delete |              event | .
|            delete |   flow-delete-post | .
|            delete |  flow-delete-topic | .
|            delete |            restore | .
|            delete |           revision | .
|            delete |                    | . (some of this seems to have null log_action: https://quarry.wmcloud.org/query/91965)
|             block |              block | probably all by log_type
|             block |            reblock | .
|             block |            unblock | .
|             block |                    | . (some of this seems to have null log_action: https://quarry.wmcloud.org/query/91965)
|           protect |             modify | probably all by log_type
|           protect |          move_prot | .
|           protect |            protect | .
|           protect |          unprotect | .

**Other admin actions** (most probably admin actions, but seem less common or less imporant):
^          log_type ^         log_action ^ notes                                                            ^
|       abusefilter |             create | probably all by log_type
|       abusefilter |             modify | .
|      contentmodel |             change | probably all by log_type
|      contentmodel |                new | .
|            patrol |             patrol | by log_action
|              move |               move | Only with special params (see suppressredirect notes).
|            rights |             rights | Should filter. Might be non-admin actionn when user downgrade's self.
|                 . |                  . | Propbably shouldn't register self-downgrades as a significant admin action anyway.
| growthexperiments |          setmentor | by log_action (setmentor)
|       massmessage |               send | Send a message to multiple users at once (massmessage)
|             merge |              merge | by log_action

**To check**:
^          log_type ^         log_action ^ notes                                                            ^
|          gblblock |         dwhitelist | Probably (right: globalblock-whitelist)
|          gblblock |          whitelist | Probably (right: globalblock-whitelist)
|        managetags |             create | Probably (right: managechangetags?)
|        managetags |         deactivate | Probably (right: managechangetags?)
|        managetags |             delete | Probably (right: managechangetags?)
|          newusers |            byemail | Probably (right: createaccount?)
|          newusers |            create2 | Probably (right: createaccount?)
|          newusers |   forcecreatelocal | Probably (right: createaccount?)
|        renameuser |         renameuser | Probably not (steward?)
| growthexperiments |            addlink | Probably not
| growthexperiments |        claimmentee | Probably not
|               tag |             update | Probably not

**Ignore**:
|              lock |    flow-lock-topic | Flow is being removed...
|              lock | flow-restore-topic | .

**Nope**:
^          log_type ^         log_action ^ notes                                                            ^
|              move |         move_redir |
|            create |             create |
|            review |                  * |
|            thanks |                  * |

## Logging table

logging - slow table
logging_userindex - fast view

`DESCRIBE logging_userindex`
```tsv
log_id	int(10) unsigned	NO
log_type	varbinary(32)	NO
log_action	varbinary(32)	YES
log_timestamp	binary(14)	NO
log_actor	bigint(20) unsigned	NO
log_namespace	int(11)	YES
log_title	varbinary(255)	YES
log_comment_id	decimal(20,0)	NO
log_params	blob	YES
log_deleted	tinyint(3) unsigned	NO
log_page	int(10) unsigned	YES
```

## AdminStats

Admin stats allows choosing stats, but has na option to just show all.

https://xtools.wmcloud.org/adminstats/pl.wikipedia.org/2024-03-23/2025-03-22?actions
	=delete
	|revisiondelete
	|logdelete
	|restore
	|reblock
	|unblock
	|reprotect
	|unprotect
	|rights
	|merge
	|import
	|abusefilter
	|contentmodel

Defaults:
delete, revisiondelete, logdelete, restore, reblock, unblock, reprotect, unprotect, rights, merge, import, abusefilter, contentmodel

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
