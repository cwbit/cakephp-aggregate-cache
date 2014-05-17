# cakephp-aggregate-cache
=======================

A Behavior plugin for CakePHP that extends the idea of counterCache and counterScope to more fields.

Note: this is a git extension of the original AggregateCache behavior by Vincent Lizzi.

## Installation
====

_[Using [Composer](http://getcomposer.org/)]_

Add the plugin to your project's `composer.json` - something like this:

	{
		"require": {
			"cwbit/cakephp-aggregate-cache": "dev-master"
		}
	}

Because this plugin has the type `cakephp-plugin` set in it's own `composer.json` composer knows to install it inside your `/Plugins` directory (rather than in the usual 'Vendor' folder). It is recommended that you add `/Plugins/AggregateCache` to your cake app's .gitignore file. (Why? [read this](http://getcomposer.org/doc/faqs/should-i-commit-the-dependencies-in-my-vendor-directory.md).)

_[Manual]_

* Download and unzip the repo (see the download button somewhere on this git page)
* Copy the resulting folder into `app/Plugin`
* Rename the folder you just copied to `AggregateCache`

_[GIT Submodule]_

In your `app` directory type:

    git submodule add -b master git://github.com/cwbit/cakephp-aggregate-cache.git Plugin/AggregateCache
    git submodule init
    git submodule update

_[GIT Clone]_

In your `app/Plugin` directory type:

    git clone -b master git://github.com/cwbit/cakephp-aggregate-cache.git AggregateCache


### Enable plugin

In 2.0 you need to enable the plugin your `app/Config/bootstrap.php` file:
```
    CakePlugin::load('AggregateCache');
```
If you are already using `CakePlugin::loadAll();`, then this is not necessary.


## Usage

The following was originally plagiarized from AggregateCache Behavior on the bakery. Modifications to the original text will be made as the plugin progresses

by vincentm8	 on August 23, 2010

AggregateCache behavior caches the result of aggregate calculations (min, max, avg, sum) in tables that are joined by a hasMany / belongsTo association. I usually think of aggregates as being easy to calculate when needed, though in situations where the aggregate value is needed more often than the underlying data changes it makes sense to cache the calculated value. Caching the result of the aggregate calculation also makes it easier to write queries that filter or sort on the aggregate value. This behavior makes caching the result of aggregate calculations easy. AggregateCache is based on the CounterCache behavior ([url]http://bakery.cakephp.org/articles/view/countercache-or-counter_cache-behavior[/url]).
To introduce the AggregateCache behavior let's use a posts and comments example. The date of the most recent comment, and the maximum and average ratings from each comment will be cached to the Post model, which will make it easy to use this information for display or as filters in other queries.


#### Posts table:
```mysql
CREATE TABLE `posts` ( 
  `id` int(10) unsigned NOT NULL auto_increment, 
  `created` datetime default NULL, 
  `modified` datetime default NULL, 
  `name` varchar(100) NOT NULL, 
  `description` mediumtext, 
  `average_rating` float default NULL, 
  `best_rating` float default NULL, 
  `latest_comment_date` datetime default NULL, 
  PRIMARY KEY  (`id`) 
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 
```
#### Comments table:
```mysql
CREATE TABLE `comments` ( 
  `id` int(10) unsigned NOT NULL auto_increment, 
  `created` datetime default NULL, 
  `modified` datetime default NULL, 
  `name` varchar(100) NOT NULL, 
  `description` mediumtext, 
  `post_id` int(10) unsigned NOT NULL, 
  `rating` int(11) default NULL, 
  `visible` tinyint(1) unsigned NOT NULL default â€˜1â€™, 
  PRIMARY KEY  (`id`), 
  KEY `comments_ibfk_1` (`post_id`), 
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) 
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 
```
#### Post model:

```php
<?php  
class Post extends AppModel { 
    var $name = 'Post'; 
    var $validate = array('name'=>'notempty'); 
    var $hasMany = array('Comment'); 
} 
?>
```

#### Comment model:
```php
<?php  
class Comment extends AppModel { 
    var $name = 'Comment'; 
     
    var $actsAs = array( 
        'AggregateCache'=>array( 
            'created'=>array(		#Syntax OPT1 - 'created' is the name of the name of the field we want to trigger by
                 'model'=>'Post',	# Post is the model we want to update with the new details
                 'max'=>'latest_comment_date' # 'Post.latest_comment_date' is the field we'll update with the 'max' function (based on 'Comment.created' as indicated above)
            ),
            array(
                 'field'=>'rating',	#Syntax OPT2 - this is more explicit and easy to read
                 'model'=>'Post', 	#The Model which holds the cache keys
                 'avg'=>'average_rating', # Post.average_rating will be set to the 'avg' of 'Comment.rating'
                 'max'=>'best_rating',	  # Post.best_rating will be set to the 'max' of 'Comment.rating'	
                 'conditions'=>array('visible'=>'1'), # only look at Comments where Comment.visible = 1
                 'recursive'=>-1	# don't need related model info
           ), 
    )); 
     
    var $validate = array( 
        'name'=>'notempty',  
        'post_id'=>'numeric',  
        'rating'=>'numeric',  
        'visible'=>'boolean' 
    ); 

    var $belongsTo = array('Post'); 
} 
?>
```

The AggregateCache behavior requires a config array that specifies, at minimum, the field and aggregate function to use in the aggregate query, and the model and field to store the cached value. The example above shows the minimal syntax in the first instance (which specifies the aggregate field as a key to the config array), and the normal syntax in the second instance. The second instance also uses the optional parameters for conditions and recursive, and specifies more than one aggregate to be calculated and stored.


To show this more clearly, the config array can specify:
```
var $actsAs = array('AggregateCache'=>array(array( 
    'field'=>'name of the field to aggregate',  
    'model'=>'belongsTo model alias to store the cached values',  
    'min'=>'field name to store the minimum value',  
    'max'=>'field name to store the maximum value', 
    'sum'=>'field name to store the sum value', 
    'avg'=>'field name to store the average value' 
    'count'=>'field name to store the count value' //allows for multiple versions of counterCache
    'conditions'=>array(), // conditions to use in the aggregate query 
    'recursive'=>-1 // recursive setting to use in the aggregate query 
))); 
```
Field and model must be specified, and at least one of min, max, sum, or avg must be specified.


The model name must be one of the keys in the belongsTo array (so if an alias is used in belongsTo, the same alias must be used in the AggregateCache config).


Specifying conditions for the aggregate query can be useful, for example, to calculate an aggregate using only the comments that have been approved for display on the site. If the conditions parameter is not provided, the conditions defined in the belongsTo association are used. (Conditions can be an empty array to specify that no conditions be used in the aggregate query.) Note: If you need to specify different conditions for different aggregates of the same field, you will need to specify 'field' explicitly and not as a key to the config array.


Specifying recursive is optional, though if your conditions donâ€™t involve a related table recursive should be set to -1 to avoid having unnecessary joins in the aggregate query.


Note: If you restrict saves to specific fields by specifying a fieldList you will need to include the foreignKey fields used to associate the model that will hold cached values, otherwise the behavior will not have the id's available to query.
