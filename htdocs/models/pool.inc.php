<?php

require_once(dirname(__FILE__) . '/../common.inc.php');

class PoolModel
{
    public $id;

    public $name;
    public $url;
    public $enabled;

    public $worker_count;

    function __construct($form = FALSE)
    {
        if ($form !== FALSE) {
            $this->id = $form['id'];

            $this->name = $form['name'];
            $this->url = $form['url'];
            $this->enabled = $form['enabled'];

            $this->worker_count = $form['worker_count'];

            $this->canonize();
        }
    }

    public function canonize()
    {
        $this->id = (int)$this->id;

        $this->name = trim($this->name);
        $this->url = trim($this->url);
        $this->enabled = $this->enabled ? 1 : 0;

        $this->worker_count = (int)$this->worker_count;
    }

    public function toggleEnabled()
    {
        $this->canonize();

        if ($this->id == 0) {
            return false;
        }
        
        $pdo = db_connect();

        $q = $pdo->prepare('
            UPDATE pool

            SET enabled = NOT enabled

            WHERE id = :pool_id
        ');

        if ($q->execute(array(':pool_id' => $this->id))) {
            $this->enabled = $this->enabled ? 0 : 1;
            return TRUE;
        }

        return FALSE;
    }

    public function refresh()
    {
        $id = (int)$this->id;

        if ($id == 0) {
            return FALSE;
        }

        $pdo = db_connect();

        $q = $pdo->prepare('
            SELECT
                p.name AS name,
                p.enabled AS enabled,
                p.url AS url,
                COUNT(wp.worker_id) AS worker_count

            FROM pool p

            LEFT OUTER JOIN worker_pool wp
            ON p.id = :pool_id

            WHERE p.id = :pool_id
        ');

        if (!$q->execute(array(':pool_id' => $id))) {
            return FALSE;
        }

        $row = $q->fetch();
        $q->closeCursor();

        if ($row === FALSE) {
            return FALSE;
        }

        $this->id = $id;

        $this->name = $row['name'];
        $this->url = $row['url'];
        $this->enabled = $row['enabled'];

        $this->worker_count = $row['worker_count'];

        return TRUE;
    }

    public function save()
    {
        if (!$this->validate()) {
            return FALSE;
        }

        $pdo = db_connect();

        $params = array(
            ':name'     => $this->name,
            ':enabled'  => $this->enabled,
            ':url'      => $this->url
        );

        if ($this->id) {
            $q = $pdo->prepare('
                UPDATE pool

                SET name = :name,
                    url = :url,
                    enabled = :enabled

                WHERE id = :id
            ');

            $params[':id'] = $this->id;
        } else {
            $q = $pdo->prepare('
                INSERT INTO pool

                (name, url, enabled)
                    VALUES
                (:name, :url, :enabled)
            ');
        }

        if (!$q->execute($params)) {
            return FALSE;
        }

        return TRUE;
    }

    public function validate()
    {
        $errors = array();

        $this->canonize();

        if ($this->name == '') {
            $errors[] = 'Pool name is required.';
        }

        if ($this->url == '') {
            $errors[] = 'Pool URL is required.';
        }

        return count($errors) ? $errors : TRUE;
    }
}

?>
