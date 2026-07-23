<?php

class SimplePager {
    public $result = [];
    public $count = 0;
    public $item_count = 0;
    public $page = 1;
    public $page_count = 1;
    public $limit = 10;

    public function __construct($sql, $params = [], $limit = 10, $page = 1) {
        global $_db;

        $this->limit = max(1, (int) $limit);
        $this->page = max(1, (int) $page);

        $countStmt = $_db->prepare("SELECT COUNT(*) FROM ($sql) AS _pager");
        $countStmt->execute($params);
        $this->count = (int) $countStmt->fetchColumn();
        $this->item_count = $this->count;

        $this->page_count = max(1, (int) ceil($this->count / $this->limit));
        $this->page = min($this->page, $this->page_count);

        $offset = ($this->page - 1) * $this->limit;

        $stmt = $_db->prepare("$sql LIMIT {$this->limit} OFFSET $offset");
        $stmt->execute($params);
        $this->result = $stmt->fetchAll();
    }

    public function html($extra = '') {
        if ($this->page_count <= 1) {
            return '';
        }

        $extra = $extra !== '' ? $extra . '&' : '';

        $html = '<nav class="pager">';
        $html .= $this->link(1, 'First', $extra);
        $html .= $this->link(max(1, $this->page - 1), 'Previous', $extra);

        for ($i = 1; $i <= $this->page_count; $i++) {
            $html .= ($i === $this->page)
                ? '<span class="current">' . $i . '</span>'
                : $this->link($i, (string) $i, $extra);
        }

        $html .= $this->link(min($this->page_count, $this->page + 1), 'Next', $extra);
        $html .= $this->link($this->page_count, 'Last', $extra);
        $html .= '</nav>';

        return $html;
    }

    private function link($page, $label, $extra) {
        return '<a href="?' . $extra . 'page=' . $page . '">' . encode($label) . '</a>';
    }
}
