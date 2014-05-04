<?php
/**
 *
 * @author colymba
 * @package GridFieldBulkEditingTools
 */
class GridFieldBulkActionEditHandler extends GridFieldBulkActionHandler
{	
	/**
	 * List of action handling methods
	 */
	private static $allowed_actions = array('edit', 'update');


	/**
	 * URL handling rules.
	 */
	private static $url_handlers = array(
		//'$Action!' => '$Action'
		'bulkedit/update' => 'update',
		'bulkedit' => 'edit'
	);


	/**
	 * Return a form for all the selected DataObject
	 * with their respective editable fields.
	 * 
	 * @return form Selected DataObject editable fields
	 */
	public function editForm()
	{
		$crumbs = $this->Breadcrumbs();
		if($crumbs && $crumbs->count()>=2)
		{
			$one_level_up = $crumbs->offsetGet($crumbs->count()-2);
		}
		
		$actions = new FieldList();
		
		$actions->push(
			FormAction::create('SaveAll', _t('GRIDFIELD_BULKMANAGER_EDIT_HANDLER.SAVE_BTN_LABEL', 'Save all'))
				->setAttribute('id', 'bulkEditingUpdateBtn')
				->addExtraClass('ss-ui-action-constructive cms-panel-link')
				->setAttribute('data-icon', 'accept')
				->setAttribute('data-url', $this->gridField->Link('bulkaction/bulkedit/update'))
				->setUseButtonTag(true)
				->setAttribute('src', '')//changes type to image so isn't hooked by default actions handlers
		);
		
		$actions->push(
			FormAction::create('Cancel', _t('GRIDFIELD_BULKMANAGER_EDIT_HANDLER.CANCEL_BTN_LABEL', 'Cancel'))
				->setAttribute('id', 'bulkEditingUpdateCancelBtn')
				->addExtraClass('ss-ui-action-destructive cms-panel-link')
				->setAttribute('data-icon', 'decline')
				->setAttribute('href', $one_level_up->Link)
				->setUseButtonTag(true)
				->setAttribute('src', '')//changes type to image so isn't hooked by default actions handlers
		);
		
    $recordList       = $this->getRecordIDList();
    $recordsFieldList = new FieldList();
    $config           = $this->component->getConfig();

    $editingCount     = count($recordList);
    $modelClass       = $this->gridField->getModelClass();
    $singleton        = singleton($modelClass);
    $titleModelClass  = (($editingCount > 1) ? $singleton->i18n_plural_name() : $singleton->i18n_singular_name());

		$header = LiteralField::create(
			'bulkEditHeader',
			'<h1 id="bulkEditHeader">' . _t('GRIDFIELD_BULKMANAGER_EDIT_HANDLER.HEADER',
				'Editing {count} {class}',
				array(
					'count' => $editingCount,
					'class' => $titleModelClass
				)
			) . '</h1>'
		);
		$recordsFieldList->push($header);

		$toggle = LiteralField::create('bulkEditToggle', '<span id="bulkEditToggle">' . _t('GRIDFIELD_BULKMANAGER_EDIT_HANDLER.TOGGLE_ALL_LINK', 'Show/Hide all') . '</span>');
		$recordsFieldList->push($toggle);
				
		foreach ( $recordList as $id )
		{						
			$record = DataObject::get_by_id($modelClass, $id);

			$recordCMSDataFields = GridFieldBulkEditingHelper::getModelCMSDataFields( $config, $this->gridField->list->dataClass );
			$recordCMSDataFields = GridFieldBulkEditingHelper::getModelFilteredDataFields($config, $recordCMSDataFields);
			$recordCMSDataFields = GridFieldBulkEditingHelper::populateCMSDataFields( $recordCMSDataFields, $this->gridField->list->dataClass, $id );
			
			//$recordCMSDataFields['ID'] = new HiddenField('ID', '', $id);			
			$recordCMSDataFields = GridFieldBulkEditingHelper::escapeFormFieldsName( $recordCMSDataFields, $id );
			
			$recordsFieldList->push(
				ToggleCompositeField::create(
					'RecordFields_'.$id,
					$record->getTitle(),					
					array_values($recordCMSDataFields)
				)
				->setHeadingLevel(4)
				->setAttribute('data-id', $id)				
				->addExtraClass('bulkEditingFieldHolder')
			);
		}
		
		$form = new Form(
			$this,
			'BulkEditingForm',
			$recordsFieldList,
			$actions
		);		
		
		if($crumbs && $crumbs->count()>=2){
			$form->Backlink = $one_level_up->Link;
		}

		return $form;
	}
	
	
	/**
	 * Creates and return the editing interface
	 * 
	 * @return string Form's HTML
	 */
	public function edit()
	{		
		$form = $this->editForm();
		$form->setTemplate('LeftAndMain_EditForm');
		$form->addExtraClass('center cms-content');
		$form->setAttribute('data-pjax-fragment', 'CurrentForm Content');
				
		Requirements::javascript(BULKEDITTOOLS_MANAGER_PATH . '/javascript/GridFieldBulkEditingForm.js');	
		Requirements::css(BULKEDITTOOLS_MANAGER_PATH . '/css/GridFieldBulkEditingForm.css');	
		Requirements::add_i18n_javascript(BULKEDITTOOLS_PATH . '/lang/js');	
		
		if($this->request->isAjax())
		{
			$response = new SS_HTTPResponse(
				Convert::raw2json(array( 'Content' => $form->forAjaxTemplate()->getValue() ))
			);
			$response->addHeader('X-Pjax', 'Content');
			$response->addHeader('Content-Type', 'text/json');
			$response->addHeader('X-Title', 'SilverStripe - Bulk '.$this->gridField->list->dataClass.' Editing');
			return $response;
		}
		else {
			$controller = $this->getToplevelController();
			return $controller->customise(array( 'Content' => $form ));
		}
	}

	
	/**
	 * Saves the changes made in the bulk edit into the dataObject
	 * 
	 * @return JSON 
	 */
	public function update()
	{		
		$data = $this->request->requestVars();
		$return = array();
		$className = $this->gridField->list->dataClass;

		if ( isset($data['url']) ) unset($data['url']);
		if ( isset($data['cacheBuster']) ) unset($data['cacheBuster']);

		foreach ($data as $recordID => $recordDataSet)
		{
			$record = DataObject::get_by_id($className, $recordID);
			foreach($recordDataSet as $recordData)
			{
				$field = preg_replace('/record_(\d+)_(\w+)/i', '$2', $recordData['name']);
				$value = $recordData['value'];

				if ( $record->hasMethod($field) )
				{				
					$list = $record->$field();
					$list->setByIDList($value);
				}
				else{
					$record->setCastedField($field, $value);
				}
			}
			$done = $record->write();
			array_push($return, array(
        'id'    => $done,
        'title' => $record->getTitle()
			));
		}

		return json_encode(array(
      'done'    => 1,
      'records' => $return
		), JSON_NUMERIC_CHECK);
	}
}