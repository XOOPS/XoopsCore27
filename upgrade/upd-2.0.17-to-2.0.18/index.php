<?php

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

/**
 * Class upgrade_2018
 */
class Upgrade_2018 extends XoopsUpgrade
{
    protected array $fields = [];

    /**
     * @return bool
     */
    public function check_config_type(): bool
    {
        $sql    = 'SHOW COLUMNS FROM ' . $this->db->prefix('config') . " LIKE 'conf_title'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return true; // table or column not found, skip this upgrade step
        }
        while (false !== ($row = $this->db->fetchArray($result))) {
            if (strtolower(trim($row['Type'])) === 'varchar(255)') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $sql
     */
    protected function query($sql)
    {
        //echo $sql . "<br>";
        if (!$this->db->exec($sql)) {
            $this->logs[] = $this->db->error();
        }
    }

    /**
     * @return bool
     */
    public function apply_config_type(): bool
    {
        $this->fields = [
            'config' => [
                'conf_title' => "varchar(255) NOT NULL default ''",
                'conf_desc' => "varchar(255) NOT NULL default ''",
            ],
            'configcategory' => ['confcat_name' => "varchar(255) NOT NULL default ''"],
        ];

        foreach ($this->fields as $table => $data) {
            foreach ($data as $field => $property) {
                $sql = 'ALTER TABLE ' . $this->db->prefix($table) . " CHANGE `$field` `$field` $property";
                $this->query($sql);
            }
        }

        return true;
    }

    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['config_type'];
    }
}

return Upgrade_2018::class;
