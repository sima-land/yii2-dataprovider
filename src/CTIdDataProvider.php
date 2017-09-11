<?php

namespace common\components;

use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\db\QueryInterface;

/**
 * Дата провайдер, использующий CTId
 *
 * Данный класс использует выборку по полю ctid, с последующей фильтрацией данных.
 * в связи с этим, в выборках страниц могут попадаться пустые страницы.
 * Так-же, количество записей на странице может отличаться от записи к записе.
 * Количество строк в выборке не подсчитывается.
 * Есть метод, который выводит количество страниц.
 * Сортировка по полям не работает.
 * Пользоваться аккуратно.
 */
class CTIdDataProvider extends ActiveDataProvider
{
    /**
     * @var integer $_totalPage Количество страниц в таблице (не в выборке).
     */
    protected $_totalPage;

    /**
     * @inheritdoc
     */
    public function prepareModels()
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }
        $result = [];
        /**
         * @var $query ActiveQuery
         */
        $query = clone $this->query;
        $query->orderBy = null;

        if (($pagination = $this->getPagination()) !== false || $this->pagination->page > $this->getTotalPage()) {
            $query->andWhere([
                'ctid' => new Expression(
                    'ANY (ARRAY(SELECT (\'(\' || :page || \',\' || s.i || \')\')::tid FROM generate_series(0,current_setting(\'block_size\')::int/4) AS s(i)))'
                )
            ]);
            $command = $query->createCommand($this->db);
            while ($this->pagination->page <= $this->getTotalPage()) {
                $page = $this->pagination->page;
                $command->bindParam('page', $page, \PDO::PARAM_STR);
                $result = $query->populate($command->queryAll());
                /**
                 * Если у нас нет данных на текущей странице, ма должны перелистнуть ее
                 * иначе, если мы отправим пустые данные будет считаться, что выборка окончена.
                 */
                if (empty($result) ) {
                    $this->pagination->setPage($page + 1);
                } else {
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Метод не возвращает количество строк, ибо из за особенностей подхода, мы применяем выборку к определенной странице.
     */
    public function getTotalCount()
    {
        return null;
    }

    /**
     * Метод возвращает общее количество страниц.
     * Необходимо для определения окончания таблицы.
     *
     * @return mixed
     */
    public function getTotalPage()
    {
        if (empty($this->_totalPage)) {
            $class = $this->query->modelClass;
            $tmp = \Yii::$app
                ->db
                ->createCommand('SELECT max(ctid) FROM ' . $class::tableName())
                ->queryColumn()[0];
            preg_match('/\d+/', $tmp, $parts);
            $this->_totalPage = $parts[0];
        }
        return $this->_totalPage;
    }
}
