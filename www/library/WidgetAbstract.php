<?php
namespace library;



abstract class WidgetAbstract
{
    protected $cache = null;
    protected $config;
    protected $prodEnv = false;
    const CONFIG_PATH = '../../../config.ini';
    const CONFIG_LOCAL_PATH = '../../../config_local.ini';

    public function __construct()
    {
        $this->config = array_merge(parse_ini_file(__DIR__ . self::CONFIG_PATH, true), parse_ini_file(__DIR__ . self::CONFIG_LOCAL_PATH, true));
        $this->prodEnv = $this->config['env']['prod'];
        $this->cache = new \Memcache();
        $this->cache->addServer('localhost', 11211);
    }

    abstract protected function getOffers($widgetId);

    public function getWidget($widgetsId)
    {
        $widgetsId = explode(',', $widgetsId);
        sort($widgetsId);
        $key = implode($widgetsId);
        if (!$this->prodEnv || $this->cache->get($key) === false) {
            $widgetContent = $this->getWidgetContent($widgetsId);
            if ($this->prodEnv && $widgetContent) {
                $this->cache->add($key, serialize($widgetContent), false, 30*60);
            }
            return $widgetContent;
        } else {
            return unserialize($this->cache->get($key));
        }
    }

    public function deleteWidget($widgetId)
    {
        $this->cache->delete($widgetId);
    }

    public function deleteAllWidget(){
        $this->cache->flush();
    }

    protected  function getWidgetContent($widgetsId)
    {
        $widgetsContent = array();
        foreach ($this->getOffers($widgetsId) as $offer) {
            $pictureSrc = $offer['offer_id'] . time();
            $widgetsContent[] = array(
                'picture' => $offer['picture'],
                'picture_our_src' => $pictureSrc,
                'price' => array(
                    'totalPrice' => $offer['price'],
                    'viewPrice' => $this->getPrice($offer['price'])
                ),
                'url' => $offer['url'],
                'id' => $offer['offer_id']
            );
            $this->addPictureInCache($pictureSrc, $offer['picture']);
        }
        return $widgetsContent;
    }

    protected function getPrice($value)
    {
        list($intValue, $floatValue) = explode('.', $value);

        return array('intValue' => $intValue ? : '0', 'floatValue' => $floatValue ? : '00');
    }

    protected function addPictureInCache($key, $url)
    {
        if ($this->cache->get($key) === false) {
            $this->cache->add($key, @file_get_contents($url));
        }
    }

}