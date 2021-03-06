<?php
namespace model;

class Rules extends AbstractModel
{

    const RULE_TYPE_SINGLE = 2;
    const RULE_TYPE_RULE = 1;

    const RULE_TYPE_SINGLE_TITLE = 'Указанный товар';
    const RULE_TYPE_RULE_TITLE = 'Правило';

    const RULE_TYPE_SINGLE_VALUE = 'single';
    const RULE_TYPE_RULE_VALUE = 'rule';

    protected $filterList = array('categoryId' => 'category_id', 'color' => 'color');

    public function insertRule($shopId, $widgetId, $rule, $position, $ruleType)
    {
        $singleRuleQuery = $this->dbh->prepare(
            "INSERT INTO rules (shop_id, widget_id, rules_type, source, position) VALUES (:shop_id, :widget_id, :rules_type, :source, :position)"
        );

        $singleRuleQuery->execute(
            array(
                ':shop_id' => $shopId,
                ':widget_id' => $widgetId,
                ':rules_type' => $ruleType,
                ':source' => $rule,
                ':position' => $position
            )
        );
    }

    public function deleteRules($widgetId)
    {
        $deleteOldRulesQuery = $this->dbh->prepare("DELETE FROM rules WHERE widget_id=:widget_id");
        $deleteOldRulesQuery->execute(array(':widget_id' => $widgetId));
    }

    public function getRulesList()
    {
        $rulesList = $this->dbh->prepare('SELECT * FROM rules_type');
        $rulesList->execute();
        return $rulesList->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getWidgetRules($widgetId)
    {
        $rulesQuery = $this->dbh->prepare(
            'SELECT w.title, w.id, w.type_id, w.position_count, w.skin_id, w.common_rule, w.shop_id, r.rules_type, r.source, r.position, wt.title AS widget_type, ws.title AS widget_skin, rt.title AS rules_name FROM widgets w LEFT JOIN rules r ON w.id=r.widget_id LEFT JOIN widget_type wt ON wt.id=w.type_id LEFT JOIN widget_skin ws ON ws.id=w.skin_id LEFT JOIN rules_type rt ON rt.id=r.rules_type WHERE w.id=:widget_id'
        );
        $rulesQuery->bindValue(':widget_id', $widgetId);
        $rulesQuery->execute();
        return $rulesQuery->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function prepareRuleToResponse($widgetId)
    {
        $outputList = array('positions' => array());
        if ($rules = $this->getWidgetRules($widgetId)) {
            foreach ($rules as $rule) {
                if (!isset($rule['rules_type']) || !isset($rule['source'])) {
                    continue;
                }
                $source = unserialize($rule['source']);
                if ($rule['rules_type'] == Rules::RULE_TYPE_SINGLE) {
                    $goodsModel = new Goods($this->dbh);
                    $source = $goodsModel->getSingleOffer(
                        array('shopId' => $rule['shop_id'], 'offerId' => $rule['source']),
                        !Goods::OFFER_IS_AVAILABLE
                    );
                    $outputList['positions'][$rule['position']]['source'] = $source;
                } elseif($rule['rules_type'] == Rules::RULE_TYPE_RULE){
                    $outputList['positions'][$rule['position']]['freeWidgetRules'] = $source;
                }
                $outputList['positions'][$rule['position']] = array_merge(
                    $outputList['positions'][$rule['position']],
                    array(
                        'typeId' => $rule['rules_type'],
                        'typeName' => $rule['rules_name']
                    )
                );
            }
            $outputList = array_merge(
                array(
                    'widgetId' => $rule['id'],
                    'typeId' => $rule['type_id'],
                    'skinId' => $rule['skin_id'],
                    'shopId' => $rule['shop_id'],
                    'commonRule' => unserialize($rule['common_rule']),
                    'typeName' => $rule['widget_type'],
                    'skinName' => $rule['widget_skin'],
                    'count' => $rule['position_count'],
                    'widgetName' => $rule['title']
                ),
                $outputList
            );
            return $outputList;
        }
        return array();
    }
}