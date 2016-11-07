<?php

namespace luya\admin\storage;

use luya\Exception;

/**
 * Query Data from Files, Filters and Images.
 *
 * Usage examples which is valid for all classes implementing the QueryTrait.
 * 
 * The below examples are wrote for file query but are are working for all classes implementing the QueryTrait like:
 * 
 * + {{\luya\admin\file\Query}}
 * + {{\luya\admin\image\Query}}
 * + {{\luya\admin\folder\Query}} 
 * 
 * ### All vs. One
 * 
 * ```php
 * return (new \luya\admin\file\Query())->where($args)->one();
 * ```
 * 
 * ```php
 * return (new \luya\admin\file\Query())->findOne($fileId);
 * ```
 * 
 * ```php
 * return (new \luya\admin\file\Query())->where($args)->all();
 * ```
 * 
 * ### Counting
 * 
 * ```php
 * return (new \luya\admin\file\Query())->where($args)->count();
 * ```
 *
 * ### Customized where condition
 * 
 * All QueryTrait classes can use different where notations:
 * 
 * ```php
 * return (new \luya\admin\file\Query())->where(['>', 'id', 1])->andWHere(['<', 'id', 3])->all();
 * ```
 * 
 * ### Offsets and Limits
 * 
 * ```php
 * return (new \luya\admin\file\Query())->where($args)->offset(5)->limit(10)->all();
 * ```
 * 
 * See the {{\luya\admin\storage\QueryTrait::where}} for more details.
 *
 * @author Basil Suter <basil@nadar.io>
 */
trait QueryTrait
{
    private $_where = [];
    
    private $_offset = null;
    
    private $_limit = null;
    
    private $_whereOperators = ['<', '<=', '>', '>=', '=', '==', 'in'];
    
    /**
     * Return an array with all item values provided for this query method.
     *
     * @return array
     */
    abstract public function getDataProvider();
    
    /**
     * Return the item for the specificy key item. If not found, false must be returned.
     * @param integer $id
     * @return array|boolean Returns the item array or false if not found.
     */
    abstract public function getItemDataProvider($id);
    
    /**
     *
     * @param array $itemArray
     */
    abstract public function createItem(array $itemArray);
    
    /**
     *
     * @param array $data
     */
    abstract public function createIteratorObject(array $data);
    
    /**
     * Process items against where filters
     *
     * @param unknown $value
     * @param unknown $field
     * @return boolean
     */
    private function arrayFilter($value, $field)
    {
        foreach ($this->_where as $expression) {
            if ($expression['field'] == $field) {
                switch ($expression['op']) {
                    case '=':
                        return ($value == $expression['value']);
                    case '==':
                        return ($value === $expression['value']);
                    case '>':
                        return ($value > $expression['value']);
                    case '>=':
                        return ($value >= $expression['value']);
                    case '<':
                        return ($value < $expression['value']);
                    case '<=':
                        return ($value <= $expression['value']);
                    case 'in':
                        return in_array($value, $expression['value']);
                }
            }
        }
    
        return true;
    }
    
    /**
     * Filter container data provider against where conditions
     *
     * @return array
     */
    private function filter()
    {
        $containerData = $this->getDataProvider();
        $whereExpression = $this->_where;
        
        if (empty($whereExpression)) {
            $data = $containerData;
        } else {
            $data = array_filter($containerData, function ($item) {
                foreach ($item as $field => $value) {
                    if (!$this->arrayFilter($value, $field)) {
                        return false;
                    }
                }
        
                return true;
            });
        }
        
        if ($this->_offset !== null) {
            $data = array_slice($data, $this->_offset, null, true);
        }

        if ($this->_limit !== null) {
            $data = array_slice($data, 0, $this->_limit, true);
        }

        return $data;
    }
    
    /**
     * Set a limition for the amount of results.
     *
     * @param integer $count The number of rows to return
     * @return \luya\admin\storage\QueryTrait
     */
    public function limit($count)
    {
        if (is_numeric($count)) {
            $this->_limit = $count;
        }
    
        return $this;
    }
    
    /**
     * Define offset start for the rows, if you defined offset to be 5 and you have 11 rows, the
     * first 5 rows will be skiped. This is commonly used to make pagination function in combination
     * with the limit() function.
     *
     * @param integer $offset Defines the amount of offset start position.
     * @return \luya\admin\storage\QueryTrait
     */
    public function offset($offset)
    {
        if (is_numeric($offset)) {
            $this->_offset = $offset;
        }
    
        return $this;
    }
    
    /**
     * Query where similar behavior of filtering items.
     *
     * Operator Filtering:
     *
     * ```php
     * where(['operator', 'field', 'value']);
     * ```
     *
     * Allowed operators
     * + **<** expression where field is smaller then value.
     * + **>** expression where field is bigger then value.
     * + **=** expression where field is equal value.
     * + **<=** expression where field is small or equal then value.
     * + **>=** expression where field is bigger or equal then value.
     * + **==** expression where field is equal to the value and even the type must be equal.
     *
     * Only one operator speific argument can be provided, to chain another expression
     * use the `andWhere()` method.
     *
     * Multi Dimension Filtering:
     *
     * The most common case for filtering items is the equal expression combined with
     * add statements.
     *
     * For example the following expression
     *
     * ```php
     * where(['=', 'id', 0])->andWhere(['=', 'name', 'footer']);
     * ```
     *
     * is equal to the short form multi deimnsion filtering expression
     *
     * ```php
     * where(['id' => 0, 'name' => 'footer']);
     * ```
     *
     * Its **not possibile** to make where conditions on the same column:
     *
     * ```php
     * where(['>', 'id', 1])->andWHere(['<', 'id', 3]);
     * ```
     *
     * This will only appaend the first condition where id is bigger then 1 and ignore the second one
     *
     * @param array $args The where defintion can be either an key-value pairing or a condition representen as array.
     * @return \luya\admin\storage\QueryTrait
     */
    public function where(array $args)
    {
        foreach ($args as $key => $value) {
            if (in_array($value, $this->_whereOperators, true)) {
                if (count($args) !== 3) {
                    throw new Exception(sprintf("Wrong where(['%s']) condition, see http://luya.io/api/cms-menu-query.html#where()-detail for all available conditions.", implode("', '", $args)));
                }
                $this->_where[] = ['op' => $args[0], 'field' => $args[1], 'value' => $args[2]];
                break;
            } else {
                $this->_where[] = ['op' => '=', 'field' => $key, 'value' => $value];
            }
        }
    
        return $this;
    }
    
    /**
     * Add another where statement to the existing, this is the case when using compare operators, as then only
     * one where definition can bet set.
     *
     * See {{luya\admin\storage\QueryTrait::where}}
     *
     * @param array $args The where defintion can be either an key-value pairing or a condition representen as array.
     * @return \luya\admin\storage\QueryTrait
     */
    public function andWhere(array $args)
    {
        return $this->where($args);
    }
    
    /**
     * Find all elementes based on the where filter.
     *
     * @return \luya\admin\storage\IteratorAbstract
     */
    public function all()
    {
        return $this->createIteratorObject($this->filter());
    }
    
    /**
     * Get the count of items
     *
     * @return integer Amount of filtere data.
     */
    public function count()
    {
        return count($this->filter());
    }
    
    /**
     * Find One based on the where condition.
     * 
     * If there are several items, it just takes the first one and does not throw an exception.
     *
     * @return \luya\admin\image\Item|\luya\admin\file\Item|\luya\admin\folder\Item
     */
    public function one()
    {
        $data = $this->filter();
        
        return (count($data) !== 0) ? $this->createItem(array_values($data)[0]): false;
    }
    
    /**
     * FindOne with the specific ID.
     *
     * @param integer $id The specific item id
     * @return \luya\admin\image\Item|\luya\admin\file\Item|\luya\admin\folder\Item
     */
    public function findOne($id)
    {
        return ($itemArray = $this->getItemDataProvider($id)) ? $this->createItem($itemArray) : false;
    }
}
