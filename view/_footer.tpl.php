
<p>(*) Inne akcje administracyjne zapisane w logach to:</p>
<ul>
<li>Zarządzanie: filtrami nadużyć (abusefilter), modelami zawartości (contentmodel), tagami edycji (managetags, update-tag), uprawnieniami (rights).</li>
<li>Dodatkowo również: globalne blokady (gblblock), patrol, setmentor, merge, massmessage-send, przenoszenie bez przekierowania (move-noredir).</li>
</ul>
</div>
<div id="footer">
	<p>Copyright &copy;2025–2026 Maciej Jaros (pl:User:Nux, en:User:Nux)</p>
	<?php if (!empty($arrTicks)) { ?>
		<div id="ticks">
			<?=L('Execution times')?> [s]:
			<ul>
				<?php foreach ($arrTicks as $strTickName=>$intDurtation) { ?>
					<li><?=sprintf("<em>%s</em> %.4f", $strTickName, $intDurtation)?></li>
				<?php } ?>
			</ul>
		</div>
	<?php } ?>
</div>
</body>
</html>