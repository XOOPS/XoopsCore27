<?php

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

/**
 * Class upgrade_2016
 */
class Upgrade_2016 extends XoopsUpgrade
{
    /**
     * @return bool
     */
    public function check_auth_db(): bool
    {
        $value = $this->getDbValue('config', 'conf_id', "`conf_name` = 'ldap_use_TLS' AND `conf_catid` = " . XOOPS_CONF_AUTH);

        return (bool) $value;
    }

    /**
     * @param string $sql
     */
    protected function query(string $sql): void
    {
        if (!$this->db->exec($sql)) {
            $this->logs[] = $this->db->error();
        }
    }

    /**
     * @return bool
     */
    public function apply_auth_db(): bool
    {
        // Insert config values
        $table = $this->db->prefix('config');
        $data  = [
            'ldap_use_TLS' => "'_MD_AM_LDAP_USETLS', '0', '_MD_AM_LDAP_USETLS_DESC', 'yesno', 'int', 21",
        ];
        foreach ($data as $name => $values) {
            if (!$this->getDbValue('config', 'conf_id', "`conf_modid`=0 AND `conf_catid`=7 AND `conf_name`='$name'")) {
                $this->query("INSERT INTO `$table` (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) " . "VALUES ( 0,7,'$name',$values)");
            }
        }

        return true;
    }

    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['auth_db'];
    }
}

return Upgrade_2016::class;
