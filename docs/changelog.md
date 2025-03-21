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
- Detailed checks for user (date-time):
	- → README.md ←
	- For each log etc show min/max timestamps.
- Add new dirs (`_temp` and `_adm`) to gitignore of the authors repo.

## Should
- ✅Some layout/structure (.tpl.php?) + CSS.
- ✅Basic i18n.
- ✅Some sortable-table library.
- ✅Icon: image by Jules78120 based on work by Alphos, Booyabazooka, and Essjay.
- ✅Link to go back to main view (from details).
- Show date range (below the table?).
- Split logs into subtypes (by `log_action`)? https://quarry.wmcloud.org/query/91886
- Optimize log query by usinbg views? As per above quarry.

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
