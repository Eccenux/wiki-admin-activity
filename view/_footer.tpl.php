
</div>
<div id="footer">
	<p>Copyright &copy;2025 Maciej Jaros (pl:User:Nux, en:User:Nux)</p>
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