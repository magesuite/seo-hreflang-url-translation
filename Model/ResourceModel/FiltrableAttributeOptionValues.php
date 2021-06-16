<?php

namespace MageSuite\SeoHreflangUrlTranslation\Model\ResourceModel;

class FiltrableAttributeOptionValues
{
    const EAV_ATTRIBUTE_OPTION_VALUE = 'eav_attribute_option_value';

    /**
     * @var \Magento\Framework\App\ResourceConnection $resourceConnection
     */
    protected $resourceConnection;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    protected $connection;

    public function __construct(\Magento\Framework\App\ResourceConnection $resourceConnection)
    {
        $this->connection = $resourceConnection->getConnection();
    }

    public function getFiltrableOptionValues($parameters)
    {
        $translationsMapped = [];
        if (!empty($parameters)) {
            $eavOptionValueTable = $this->connection->getTableName('eav_attribute_option_value');
            $subSelect = $this->connection->select();
            $subSelect->from($eavOptionValueTable, 'option_id')
                ->where('value IN (?)', $parameters);

            $select = $this->connection->select();
            $select->from($eavOptionValueTable, ['option_id',' store_id', 'value'])
                ->where('option_id IN (?)', new \Zend_Db_Expr($subSelect));

            foreach ($this->connection->fetchAll($select) as $item) {
                $translationsMapped[$item['option_id']][$item['store_id']] =  $item['value'];
            }
        }

        return $translationsMapped;
    }
}
