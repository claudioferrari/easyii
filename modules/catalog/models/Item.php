<?php
namespace yii\easyii\modules\catalog\models;

use Yii;
use yii\behaviors\SluggableBehavior;
use yii\easyii\behaviors\SeoBehavior;
use yii\easyii\behaviors\SortableModel;
use yii\easyii\models\Photo;

class Item extends \yii\easyii\components\ActiveRecord
{

    public static function tableName()
    {
        return 'easyii_catalog_items';
    }

    public function rules()
    {
        return [
            ['title', 'required'],
            ['title', 'trim'],
            ['title', 'string', 'max' => 128],
            ['thumb', 'image'],
            ['description', 'safe'],
            ['slug', 'match', 'pattern' => self::$slugPattern, 'message' => Yii::t('easyii', 'Slug can contain only 0-9, a-z and "-" characters (max: 128).')],
            ['slug', 'default', 'value' => null],
            ['slug', 'unique', 'when' => function($model){
                return $model->slug && !self::autoSlug();
            }]
        ];
    }

    public function attributeLabels()
    {
        return [
            'title' => Yii::t('easyii/catalog', 'Title'),
            'thumb' => Yii::t('easyii', 'Image'),
            'description' => Yii::t('easyii', 'Description'),
            'slug' => Yii::t('easyii', 'Slug'),
        ];
    }

    public function behaviors()
    {
        return [
            SortableModel::className(),
            'seo' => SeoBehavior::className(),
        ];
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if(!$this->data || (!is_object($this->data) && !is_array($this->data))){
                $this->data = new \stdClass();
            }
            $this->data = json_encode($this->data);
            return true;
        } else {
            return false;
        }
    }

    public function beforeValidate()
    {
        if(self::autoSlug() && (!$this->isNewRecord || ($this->isNewRecord && $this->slug == ''))){
            $this->attachBehavior('sluggable', [
                'class' => SluggableBehavior::className(),
                'attribute' => 'title',
                'ensureUnique' => true
            ]);
        }
        return parent::beforeValidate();
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->data = $this->data !== '' ? json_decode($this->data) : [];
    }

    public function getPhotos()
    {
        return $this->hasMany(Photo::className(), ['item_id' => 'item_id'])->where(['model' => Item::className()])->sort();
    }

    public function getCategory()
    {
        return $this->hasOne(Category::className(), ['category_id' => 'category_id']);
    }

    public function afterDelete()
    {
        parent::afterDelete();

        foreach($this->getPhotos()->all() as $photo){
            $photo->delete();
        }

        if($this->thumb) {
            @unlink(Yii::getAlias('@webroot') . $this->thumb);
        }
    }

    public static function autoSlug()
    {
        return Yii::$app->getModule('admin')->activeModules['catalog']->settings['itemAutoSlug'];
    }
}