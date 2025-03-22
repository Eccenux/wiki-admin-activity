## Must
- ✅PoC getting admin data.
- ✅Ns:0 edits.
- ✅Cache for ns:0 data (slow as in extra ~10s).
- ✅Update host naming conventions: https://wikitech.wikimedia.org/wiki/Help:Wiki_Replicas#Connecting_to_the_database_replicas
- ✅Same stats but for a single user od any type:
	- ✅Add links in the table (`...username=encod($...)`).
	- ✅From username get actor_id.
	- ✅Get same table.
- ✅Optimize single user edits -> revision_userindex
- ✅Optimize log query by using special views. As per quarry: https://quarry.wmcloud.org/query/91886
- ✅Add new dirs (`_temp` and `_adm`) to gitignore of the authors repo.
- Verify admin actions
	- Will have to use `log_action` too as `delete-delete_redir` is a type-action that an editor can do too (not to be confused with suppressredirect).
	- suppressredirect on the other hand is a move-move log.
- Detailed checks for user (date-time):
	- → README.md ←
	- For each log etc show min/max timestamps.

adminstats
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

## Should
- ✅Some layout/structure (.tpl.php?) + CSS.
- ✅Basic i18n.
- ✅Some sortable-table library.
- ✅Icon: image by Jules78120 based on work by Alphos, Booyabazooka, and Essjay.
- ✅Link to go back to main view (from details).
- Show date range (below the table?).

## Maybe
- Weighted sum? (edits/10 + sum)
- Prpepare a list of projects (for autocomplete or validation)?
	- https://wikitech.wikimedia.org/wiki/Help:Wiki_Replicas#Connecting_to_the_database_replicas
	- https://db-names.toolforge.org/
- Project from GET.
	- Cache per dbname.
	- Simple form?
	- $_GET/$_REQ (cookies welcome?) + validate as [a-z]{2,10}(!)
- Some kind of i18n?
