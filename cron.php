<?php
require_once __DIR__.'/library/Config.php';
use library\Config;

class initBase
{
    const BASE_TMP_NAME = 'base_tmp.xml';
    const BASE_NAME = 'base_db.xml';

    private $backupName = '';
    private $projectDir = __DIR__;
    /**
     * @var \PDO
     */
    private $dbh;
    private $config = array();

    public function __construct()
    {
        chdir($this->projectDir);
        $this->config = Config::getInstance()->getConfig();
        Config::getInstance()->setBusyStatus(true);
    }

    public function updateBase()
    {
        foreach ($this->getBaseUrl() as $shopName => $baseUrl) {
            if ($this->downloadBase($baseUrl, $shopName)) {
                $this->updateDB($shopName);
                $this->removeTmp();
            }
        }
    }

    private function getBaseUrl()
    {
        return $this->config['base_url'];
    }

    private function downloadBase($baseUrl, $shopName)
    {
        if ($this->fileExists($baseUrl) && @copy($baseUrl, self::BASE_TMP_NAME)) {
            rename(self::BASE_TMP_NAME, self::BASE_NAME);
        } else {
            return false;
        }
        $this->makeBackup($shopName);
        return true;
    }

    private function fileExists($baseUrl)
    {
        $file_headers = @get_headers($baseUrl);
        if (strpos($file_headers[0], 'HTTP/1.1 200 OK') === false) {
            return false;
        } else {
            return true;
        }
    }

    private function setupBackup($shopName)
    {
        if ($backupName = $this->getLastBackup($this->prependBackupFolder($shopName))) {
            copy(
                $this->projectDir . '/' . $this->config['backup']['folder'] . $shopName . '/' . $backupName,
                self::BASE_NAME
            );
        }
    }

    private function getLastBackup($filesList)
    {
        if ($filesList) {
            return array_shift($filesList);
        }
        return false;
    }

    private function prependBackupFolder($shopName)
    {
        chdir($this->projectDir . '/' . $this->config['backup']['folder'] . $shopName);
        $filesList = glob('*.xml');
        rsort($filesList);
        if (count($filesList) > $this->config['backup']['max_backup_file']) {
            foreach (array_slice($filesList, $this->config['backup']['max_backup_file']) as $fileName) {
                unlink($fileName);
            }
        }
        chdir($this->projectDir);
        return $filesList;
    }

    private function makeBackup($shopName)
    {
        $this->backupName = $this->config['backup']['folder'] . $shopName . '/' . date('YmdHi') . '.xml';
        if (!file_exists($this->projectDir . '/' . $this->config['backup']['folder'] . $shopName)) {
            mkdir($this->projectDir . '/' . $this->config['backup']['folder'] . $shopName, 0777);
        }
        $this->prependBackupFolder($shopName);
        if (@copy(self::BASE_NAME, $this->backupName)) {
            chmod($this->backupName, 0777);
        }
    }

    private function removeTmp()
    {
        unlink(self::BASE_NAME);
    }

    private function updateDB($shopName)
    {
        if (file_exists(self::BASE_NAME)) {
            try {
                $this->dbh = Config::getInstance()->getDbConnection();
                $newData = simplexml_load_file(self::BASE_NAME);
                $shopId = $this->getShopId($shopName, (string)$newData->shop->url);
                $this->addCurrency($shopId, $newData->shop->currencies);
                $this->updateCategories($shopId, $newData->shop->categories);
                $this->updateWithTmpTable($shopId, $newData->shop->offers);
            } catch (\Exception $e) {
                $this->setupBackup($shopName);
            }
        } else {
            $this->setupBackup($shopName);
        }
    }

    private function addCurrency($shopId, $data = array())
    {
        $currencyList = array();
        $currencyQuery = $this->dbh->prepare('DELETE FROM currency WHERE shop_id=:shop_id');
        $currencyQuery->execute(array(':shop_id' => $shopId));
        $currencyQuery = $this->dbh->prepare(
            'INSERT INTO currency (currency_id, rate, shop_id) VALUES (:currency_id, :rate, :shop_id) ON DUPLICATE KEY UPDATE rate=:rate'
        );
        foreach ($data->currency as $value) {
            $currencyList[$shopId][(string)$value->attributes()->id] = (string)$value->attributes()->rate;
            $currencyQuery->execute(
                array(
                    ':currency_id' => (string)$value->attributes()->id,
                    ':rate' => (string)$value->attributes()->rate,
                    ':shop_id' => $shopId
                )
            );
        }
        return $currencyList;
    }

    private function getShopId($shopName, $url = '')
    {
        $STH = $this->dbh->prepare('SELECT id from shops WHERE title = :shop_name LIMIT 1');
        $STH->bindValue(':shop_name', $shopName);
        $STH->execute();
        if (!$shopId = $STH->fetch()) {
            $shopId['id'] = $this->addShop($shopName, $url);
        }
        return $shopId['id'];
    }

    private function addShop($shopName, $url)
    {
        $stmt = $this->dbh->prepare("INSERT INTO shops (title, url) values (:title, :url)");
        $stmt->bindValue(':title', $shopName);
        $stmt->bindValue(':url', $url);
        $stmt->execute();
        return $this->dbh->lastInsertId();
    }

    private function updateCategories($shopId, $categories)
    {
        $stmt = $this->dbh->prepare(
            "INSERT LOW_PRIORITY INTO categories (category_id, shop_id,parent_id, title) VALUES (:category_id, :shop_id, :parent_id, :title) ON DUPLICATE KEY UPDATE parent_id=:parent_id, title=:title"
        );
        $this->dbh->beginTransaction();
        foreach ($categories->children() as $category) {
            $stmt->execute(
                array(
                    'category_id' => (int)$category->attributes()->id,
                    'shop_id' => (int)$shopId,
                    'parent_id' => (int)$category->attributes()->parentId,
                    'title' => (string)$category
                )
            );
        }
        $this->dbh->commit();
    }

    private function updateWithTmpTable($shopId, $offers)
    {
        $this->dbh->beginTransaction();
        $this->createTmpTable();
        $this->copyDataToTmpTable();
        $this->resetAvailableValue($shopId);
        $this->updateDataInTmpTable($shopId, $offers);
        $this->dbh->prepare('DROP TABLE goods')->execute();
        $this->dbh->prepare('RENAME TABLE goods_tmp TO goods')->execute();
        $this->dbh->commit();
        return true;
    }

    private function updateDataInTmpTable($shopId, $offers)
    {
        $offerUpdate = $this->dbh->prepare(
            "INSERT INTO goods_tmp (offer_id, category_id,shop_id, is_available, url, price, currency, picture, title, common_data, color) VALUES (:offer_id, :category_id, :shop_id, :is_available, :url, :price, :currency, :picture, :title, :common_data, :color) ON DUPLICATE KEY UPDATE category_id=:category_id, is_available=:is_available, url=:url, price=:price, currency=:currency, picture=:picture, title=:title, common_data=:common_data, color=:color"
        );
        foreach ($offers->children() as $offer) {
            $data = $this->prepareCommonData($offer);
            $offerUpdate->execute(
                array(
                    'offer_id' => $data['attributes']['id'],
                    'category_id' => $data['categoryId'],
                    'shop_id' => (int)$shopId,
                    'is_available' => (boolean)$data['attributes']['available'],
                    'url' => $data['url'],
                    'price' => $data['price'],
                    'currency' => $data['currencyId'],
                    'picture' => $data['picture'],
                    'title' => $data['model'],
                    'common_data' => serialize($this->prepareCommonData($offer)),
                    'color' => $data['param']['color']
                )
            );
        }
    }

    private function prepareCommonData($data)
    {
        $paramsNameConvert = array('Цвет' => 'color', 'Размеры' => 'size');
        $params = array('param' => array(), 'attributes' => array());
        foreach ($data->param as $value) {
            $params['param'][$paramsNameConvert[(string)$value->attributes()->name]] = (string)$value;
        }
        foreach ($data->attributes() as $key => $value) {
            $params['attributes'][$key] = (string)$value;
        }
        $preparedData = array();
        foreach ($data as $key=>$value){
            $preparedData[(string)$key] = (string)$value;
        }
        return array_merge($preparedData, $params);
    }

    private function resetAvailableValue($shopId)
    {
        $offerAvaliableReset = $this->dbh->prepare('UPDATE goods_tmp SET is_available = 0 WHERE shop_id=:shop_id');
        $offerAvaliableReset->bindValue(':shop_id', $shopId);
        $offerAvaliableReset->execute();
    }

    private function createTmpTable()
    {
        $this->dbh->prepare($this->createTmpTableCode('goods_tmp'))->execute();
    }

    private function copyDataToTmpTable()
    {
        $copyDataInTmpTable = $this->dbh->prepare('INSERT INTO goods_tmp SELECT * FROM goods');
        $copyDataInTmpTable->execute();
    }

    private function createTmpTableCode($tableName)
    {
        $key_prefix = time();
        return <<<EOL
CREATE TABLE `{$tableName}` (
	`category_id` INT(11) UNSIGNED NOT NULL,
	`shop_id` INT(11) NULL DEFAULT NULL,
	`offer_id` VARCHAR(20) NULL DEFAULT NULL,
	`price` VARCHAR(20) NULL DEFAULT NULL,
	`url` VARCHAR(255) NULL DEFAULT NULL,
	`currency` VARCHAR(20) NULL DEFAULT NULL,
	`picture` VARCHAR(255) NULL DEFAULT NULL,
	`common_data` TEXT NULL,
	`is_available` INT(1) NULL DEFAULT NULL,
	`title` VARCHAR(255) NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	`color` VARCHAR(50) NULL DEFAULT NULL,
	UNIQUE INDEX `offer_id` (`offer_id`, `shop_id`),
	INDEX `category_id` (`category_id`, `shop_id`),
	INDEX `shop_id` (`shop_id`),
	CONSTRAINT `FK_goods_categories_{$key_prefix}` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_goods_shops_{$key_prefix}` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

EOL;

    }

    public function __destruct()
    {
        Config::getInstance()->setBusyStatus(false);
        $this->dbh = null;
    }
}

$db = new initBase();
$db->updateBase();