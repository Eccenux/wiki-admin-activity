<?php

// Usage example
if (defined('EXAMPLE_USAGE')) {
	$mapping = [
		'user_id' => ['class' => 'user-id'],
		'user_link' => ['_render' => 'raw', 'class' => 'main  user-link'],
		'user_name' => ['_render' => function($value, $row) {
			$url = "index.php?" . http_build_query(['action' => 'details', 'user_id' => $row['user_id']], '', '&amp;');
			$content = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
			return "<a href='{$url}'>{$content}</a>";
		}],
	];
	$columns = [
		['_cell' => 'UID', 'class' => 'user-id', 'title' => 'user_id'],
		['_cell' => 'Some user link'],
		['_cell' => 'Admin'],
	];

	$printer = new TablePrinter($mapping);
	$head = $printer->renderHead($columns);
	$html = <<<EOS
		<table class='wikitable sortable' border='1'>
		<thead>{$head}</thead>
		<tbody>
	EOS;
	foreach ($data as $admin) {
		$html .= $printer->renderRow($admin);
	}
	$html .= "</tbody></table>";
	return $html;
}

/**
 * Simple HTML table renderer with configurable column mappings.
 */
class TablePrinter {
	/** @var array Column mapping configuration. */
	private $mapping;

	/**
	 * @param array $rawMapping Mapping configuration:
	 *  key => [
	 *      '_render' => callable|string|null, // custom render function or 'raw' or null/none (defaults to-html)
	 *      'class' => string|null,
	 *      'title' => string|null,
	 * 		...other cell attributes...
	 *  ]
	 */
	public function __construct(array $rawMapping) {
		$this->mapping = [];
		foreach ($rawMapping as $key => $raw) {
			$cfg = $this->buildConfig($raw);
			$this->mapping[$key] = $cfg;
		}
	}

	private function buildConfig(array $raw): array {
		$cfg = [];
		// attrs
		$cfg['attrs'] = $this->buildAttrs($raw);
		// rendering type
		$cfg['render'] = 'default';
		if (isset($raw['_render'])) {
			$renderer = $raw['_render'];
			if (is_callable($renderer)) {
				$cfg['render'] = 'callable';
				$cfg['render_fun'] = $renderer;
			} elseif ($renderer === 'raw') {
				$cfg['render'] = $renderer;
			}
		}
		return $cfg;
	}

	/** Render a value with given mapping. */
	private function renderValue($value, array $cfg, array $row = []): string {
		$content = '';
		switch ($cfg['render']) {
			case 'callable':
				$content = call_user_func($cfg['render_fun'], $value, $row);
				break;
			case 'raw':
				$content = (string)$value;
				break;
			default:
				$content = htmlspecialchars((string)$value, ENT_QUOTES);
				break;
		}
		return $content;
	}

	/**
	 * Renders the <thead> row from given column definitions.
	 * Each column is an array with keys: '_cell' (content) and optional attributes and optional '_render' (like in constructor mapping).
	 */
	public function renderHead(array $columns): string {
		$html = "<tr>\n";
		foreach ($columns as $col) {
			$value = $col['_cell'] ?? '';

			$cfg = $this->buildConfig($col);
			$attrs = $cfg['attrs'];
			$content = $this->renderValue($value, $cfg);

			$html .= "\t<th{$attrs}>{$content}</th>\n";
		}
		$html .= "</tr>\n";
		return $html;
	}

	/**
	 * Renders a <tr> for given associative $row.
	 * Uses mapping to control rendering and attributes.
	 */
	public function renderRow(array $row): string {
		$html = "<tr>\n";
		foreach ($this->mapping as $key => $cfg) {
			$value = $row[$key] ?? '';

			$attrs = $cfg['attrs'];
			$content = $this->renderValue($value, $cfg, $row);

			$html .= "\t<td{$attrs}>{$content}</td>\n";
		}
		$html .= "</tr>\n";
		return $html;
	}

	/** Builds HTML attribute string. */
	private function buildAttrs(array $col): string {
		$attrs = '';
		foreach ($col as $name => $value) {
			// skip '_cell' etc
			if (strpos($name, '_') === 0) {
				continue;
			}
			// add attr
			$attrs .= " {$name}='" . htmlspecialchars($value, ENT_QUOTES) . "'";
		}
		return $attrs;
	}
}
