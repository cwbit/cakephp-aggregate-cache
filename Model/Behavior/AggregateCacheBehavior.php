<?php 
/** 
 * AggregateCache Behavior 
 * 
 * Usage: 
 * var $actsAs = array('AggregateCache'=>array(array( 
 *   'field'=>'name of the field to aggregate', 
 *   'model'=>'belongsTo model alias to store the cached values', 
 *   'min'=>'field name to store the minimum value', 
 *   'max'=>'field name to store the maximum value', 
 *   'sum'=>'field name to store the sum value', 
 *   'avg'=>'field name to store the average value' 
 *   'count' => 'field name to store the count value', 
 *   'conditions'=>array(), // conditions to use in the aggregate query 
 *   'recursive'=>-1 // recursive setting to use in the aggregate query 
 *  ))); 
 * 
 * Example: 
 * class Comments extends AppModel { 
 *   var $name = 'Comment'; 
 *   var $actsAs = array( 
 *     'AggregateCache'=>array( 
 *         'rating'=>array('model'=>'Post', 'avg'=>'average_rating', 'max'=>'best_rating'), 
 *         array('field'=>'created', 'model'=>'Post', 'max'=>'latest_comment_date', 'conditions'=>array('visible'=>'1'), 'recursive'=>-1)
 *     )); 
 *   var $belongsTo = array('Post'); 
 * } 
 * 
 * Each element of the configuration array should be an array that specifies: 
 * A field on which the aggregate values should be calculated. The field name may instead be given as a key in the configuration array.
 * A model that will store the cached aggregates. The model name must match the alias used for the model in the belongsTo array.
 * At least one aggregate function to calculate and the field in the related model that will store the calculated value.
 *    Aggregates available are: min, max, avg, sum, count 
 * A conditions array may be provided to filter the query used to calculate aggregates. 
 *    If not specified, the conditions of the belongsTo association will be used. 
 * A recursive value may be specified for the aggregate query. If not specified Cake's default will be used. 
 *    If it's not necessary to use conditions involving a related table, setting recursive to -1 will make the aggregate query more efficient.
 * 
 * @author CWBIT (original author Vincent Lizzi)
 * @version 2014-01-25 
 */ 
class AggregateCacheBehavior extends ModelBehavior { 

    var $foreignTableIDs = array(); 
    var $config = array(); 
    var $functions = array('min', 'max', 'avg', 'sum', 'count'); 

    function setup(Model $model, $config = []) { 
        foreach ($config as $k => $aggregate) { 
            if (empty($aggregate['field'])) { 
                $aggregate['field'] = $k; 
            } 
            if (!empty($aggregate['field']) && !empty($aggregate['model'])) { 
                $this->config[] = $aggregate; 
            } 
        } 
    } 

    function __updateCache(Model $model, $aggregate, $foreignKey, $foreignId) { 
        $assocModel = & $model->{$aggregate['model']}; 
        $calculations = array(); 
        foreach ($aggregate as $function => $cacheField) { 
            if (!in_array($function, $this->functions)) { 
                continue; 
            } 
            $calculations[] = $function . '(' . $model->name . '.' . $aggregate['field'] . ') ' . $function . '_value'; 
        } 
        if (count($calculations) > 0) { 
            $conditions = array($model->name . '.' . $foreignKey => $foreignId); 
            if (array_key_exists('conditions', $aggregate)) { 
                $conditions = am($conditions, $aggregate['conditions']); 
            } else { 
                $conditions = am($conditions, $model->belongsTo[$aggregate['model']]['conditions']); 
            } 
            $recursive = (array_key_exists('recursive', $aggregate)) ? $aggregate['recursive'] : null; 
            $results = $model->find('first', array( 
                        'fields' => $calculations, 
                        'conditions' => $conditions, 
                        'recursive' => $recursive, 
                        'group' => $model->name . '.' . $foreignKey, 
                    )); 
            $newValues = array(); 
            foreach ($aggregate as $function => $cacheField) { 
                if (!in_array($function, $this->functions)) { 
                    continue; 
                }
                if (empty($results)) {
                    $newValues[$cacheField] = 0;
                } else {
                    $newValues[$cacheField] = $results[0][$function . '_value'];
                }
            } 
            $assocModel->id = $foreignId; 
            $assocModel->save($newValues, false, array_keys($newValues)); 
        } 
    } 

    function afterSave(Model $model, $created, $options = []) { 
        foreach ($this->config as $aggregate) { 
            if (!array_key_exists($aggregate['model'], $model->belongsTo)) { 
                continue; 
            } 
            $foreignKey = $model->belongsTo[$aggregate['model']]['foreignKey']; 
            $foreignId = $model->data[$model->name][$foreignKey]; 
            $this->__updateCache($model, $aggregate, $foreignKey, $foreignId); 
        } 
    } 

    function beforeDelete(Model $model, $cascade = true) { 
        foreach ($model->belongsTo as $assocKey => $assocData) { 
            $this->foreignTableIDs[$assocData['className']] = $model->field($assocData['foreignKey']); 
        } 
        return true; 
    } 

    function afterDelete(Model $model) { 
        foreach ($this->config as $aggregate) { 
            if (!array_key_exists($aggregate['model'], $model->belongsTo)) { 
                continue; 
            } 
            $foreignKey = $model->belongsTo[$aggregate['model']]['foreignKey']; 
            $foreignId = $this->foreignTableIDs[$aggregate['model']]; 
            $this->__updateCache($model, $aggregate, $foreignKey, $foreignId); 
        } 
    } 

} 
?>
