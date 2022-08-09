<?php

namespace OsTheNeo\NovaFields;

use OsTheNeo\NovaFields\Rules\ArrayRules;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ResourceRelationshipGuesser;
use Laravel\Nova\Http\Requests\NovaRequest;

class BelongsToManyField extends Field {
	/**
	 * The callback to be used for the field's options.
	 * @var array|callable
	 */
	private $optionsCallback;
	public $showOnIndex = true;
	public $showOnDetail = true;
	public $isAction = false;
	public $height = '350px';
	public $viewable = true;
	public $showAsList = false;
	public $pivotData = [];
	/**
	 * The field's component.
	 * @var string
	 */
	public $component = 'BelongsToManyField';
	public $relationModel;
	public $label = null;
	public $trackBy = 'id';
	
	/**
	 * Create a new field.
	 * @param string $name
	 * @param null $attribute
	 * @param null $resource
	 */
	
	public function __construct($name, $attribute = null, $resource = null ){
		parent::__construct($name, $attribute);
		
		$resource       = $resource ?? ResourceRelationshipGuesser::guessResource($name);
		$this->resource = $resource;
		
		if ($this->label === null){
			$this->optionsLabel(($resource)::$this->title ?? 'name');
		}
		
		
		$this->resourceClass          = $resource;
		$this->resourceName           = $resource::uriKey();
		$this->manyToManyRelationship = $this->attribute;
		
		
		
		$this->fillUsing(function ($request, $model, $attribute, $requestAttribute) use ($resource){
			
			if (is_subclass_of($model, 'Illuminate\Database\Eloquent\Model')){
				$model::saved(function ($model) use ($attribute, $request){
					$inp = json_decode($request->input($attribute), true);
					if ($inp !== null){
						$values = array_column($inp, 'id');
					}else{
						$values = [];
					}
					if (!empty($this->pivot())){
						$values = array_fill_keys($values, $this->pivot());
					}
					$model->$attribute()->sync($values);
				});
				$request->except($attribute);
			}
		});
		
		$this->localize();
	}
	
	public function optionsLabel(string $optionsLabel){
		$this->label = $optionsLabel;
		return $this->withMeta(['optionsLabel' => $this->label]);
	}
	
	
	
	public function options(array $options){
		return $this->withMeta(['options' => $options]);
	}
	
	public function relationModel($model){
		$this->relationModel = $model;
		return $this;
	}
	
	public function isAction($isAction = true){
		$this->isAction = $isAction;
		return $this->withMeta(['height' => $this->height]);
	}

	public function showAsListInDetail($showAsList = true){
		$this->showAsList = $showAsList;
		return $this->withMeta(['showAsList' => $this->showAsList]);
	}
	
	public function viewable($viewable = true){
		$this->viewable = $viewable;
		return $this;
	}
	
	public function setMultiselectProps($props){
		return $this->withMeta(['multiselectOptions' => $props]);
	}
	
	public function setMultiselectSlots($slots){
		return $this->withMeta(['multiselectSlots' => $slots]);
	}
	
	public function dependsOn($dependsOnField, $tableKey){
		return $this->withMeta([
			'dependsOn'    => $dependsOnField,
			'dependsOnKey' => $tableKey,
		]);
	}
	
	public function rules($rules){
		$rules       = ($rules instanceof Rule || is_string($rules)) ? func_get_args() : $rules;
		$this->rules = [new ArrayRules($rules)];
		return $this;
	}
	
	public function resolve($resource, $attribute = null){
		if ($this->isAction) {
			parent::resolve($resource, $attribute);
		} else {
			parent::resolve($resource, $attribute);
			$value = json_decode($resource->{$this->attribute});
			if ($value) {
				$this->value = $value;
			}
		}
	}
	
	public function jsonSerialize(): array{
		$this->resolveOptions();
		return array_merge([
			'attribute'                => $this->attribute,
			'component'                => $this->component(),
			'helpText'                 => $this->getHelpText(),
			'indexName'                => $this->name,
			'name'                     => $this->name,
			'nullable'                 => $this->nullable,
			'optionsLabel'             => $this->label,
			'trackBy'                  => $this->trackBy,
			'panel'                    => $this->panel,
			'prefixComponent'          => true,
			'readonly'                 => $this->isReadonly(app(NovaRequest::class)),
			'required'                 => $this->isRequired(app(NovaRequest::class)),
			'resourceNameRelationship' => $this->resourceName,
			'sortable'                 => $this->sortable,
			'sortableUriKey'           => $this->sortableUriKey(),
			'stacked'                  => $this->stacked,
			'textAlign'                => $this->textAlign,
			'value'                    => $this->value,
			'viewable'                 => $this->viewable,
			'validationKey'            => $this->validationKey(),
		], $this->meta());
	}
	
	public function pivot(){
		return $this->pivotData;
	}
	
	public function setPivot(array $attributes){
		$this->pivotData = $attributes;
		return $this;
	}
	
	protected function localize(){
		$this->setMultiselectProps([
			'selectLabel'        => __('belongs-to-many-field-nova::vue-multiselect.select_label'),
			'selectGroupLabel'   => __('belongs-to-many-field-nova::vue-multiselect.select_group_label'),
			'selectedLabel'      => __('belongs-to-many-field-nova::vue-multiselect.selected_label'),
			'deselectLabel'      => __('belongs-to-many-field-nova::vue-multiselect.deselect_label'),
			'deselectGroupLabel' => __('belongs-to-many-field-nova::vue-multiselect.deselect_group_label'),
		]);
		$this->setMultiselectSlots([
			'noOptions' => $this->getNoOptionsSlot(),
			'noResult'  => $this->getNoResultSlot()
		]);
	}
	
	protected function getNoOptionsSlot(){
		return __('belongs-to-many-field-nova::vue-multiselect.no_options');
	}
	
	protected function getNoResultSlot(){
		return __('belongs-to-many-field-nova::vue-multiselect.no_result');
	}
	
	private function resolveOptions(): void{
		if (isset($this->optionsCallback)){
			if (is_callable($this->optionsCallback)){
				$this->withMeta(['options' => call_user_func($this->optionsCallback)]);
			}else{
				$this->withMeta(['options' => collect($this->optionsCallback)]);
			}
		}
	}
	
	protected function fillAttributeFromRequest(NovaRequest $request, $requestAttribute, $model, $attribute){
		if ($request->exists($requestAttribute)){
			$model->{$attribute} = $request[$requestAttribute];
		}
	}
	
}
