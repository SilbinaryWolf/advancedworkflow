<?php

class FrontendWorkflowForm extends Form{

	/*private static $allowed_actions = array(
		'httpSubmission',
	);*/

	/**
	 * @var array
	 */
	/*private static $url_handlers = array(
		'field/$FieldName!' => 'handleField',
		'POST ' => 'httpSubmission',
		'GET ' => 'httpSubmission',
		'HEAD ' => 'httpSubmission',
	);*/

	public function httpSubmission($request) {
		// Populate the form
		$vars = $request->requestVars();
		$this->loadDataFrom($vars, true);
		
		// Determine the action button clicked
		$funcName = null;
		foreach($vars as $paramName => $paramVal) {
			if(substr($paramName,0,7) == 'action_') {
				
				// Added for frontend workflow form - get / set transitionID on controller, 
				// unset action and replace with doFrontEndAction action
				if(substr($paramName,0,18) == 'action_transition_') {
					$this->controller->transitionID = substr($paramName,strrpos($paramName,'_') +1);
					unset($vars['action_transition_' . $this->controller->transitionID]);
					$vars['action_doFrontEndAction'] = 'doFrontEndAction';
					$paramName = 'action_doFrontEndAction';
					$paramVal = 'doFrontEndAction';
				}
			
				// Break off querystring arguments included in the action
				if(strpos($paramName,'?') !== false) {
					list($paramName, $paramVars) = explode('?', $paramName, 2);
					$newRequestParams = array();
					parse_str($paramVars, $newRequestParams);
					$vars = array_merge((array)$vars, (array)$newRequestParams);
				}
			
				// Cleanup action_, _x and _y from image fields
				$funcName = preg_replace(array('/^action_/','/_x$|_y$/'),'',$paramName);
				break;
			}
		}

		// If the action wasnt' set, choose the default on the form.
		if(!isset($funcName) && $defaultAction = $this->defaultAction()){
			$funcName = $defaultAction->actionName();
		}
		
		if(isset($funcName)) {
			$this->setButtonClicked($funcName);
			Form::set_current_action($funcName);
		}
	
		// Permission checks (first on controller, then falling back to form)
		if(
			// Ensure that the action is actually a button or method on the form,
			// and not just a method on the controller.
			$this->controller->hasMethod($funcName)
			&& !$this->controller->checkAccessAction($funcName)
			// If a button exists, allow it on the controller
			&& !$this->Actions()->fieldByName('action_' . $funcName)
		) {
			return $this->httpError(
				403, 
				sprintf(_t('AdvancedWorkflowFrontendForm.ACTIONCONTROLLERCHECK', 'Action "%s" not allowed on controller (Class: %s)'), $funcName, get_class($this->controller))
			);
		} elseif(
			$this->hasMethod($funcName)
			&& !$this->checkAccessAction($funcName)
			// No checks for button existence or $allowed_actions is performed -
			// all form methods are callable (e.g. the legacy "callfieldmethod()")
		) {
			return $this->httpError(
				403, 
				sprintf(_t('AdvancedWorkflowFrontendForm.ACTIONFORMCHECK','Action "%s" not allowed on form (Name: "%s")'), $funcName, $this->Name())
			);
		}
	
		if ($wfTransition = $this->controller->getCurrentTransition()) {
			$wfTransType = $wfTransition->Type;
		} else {
			$wfTransType = null; //ie. when a custom Form Action is defined in WorkflowAction
		}
		
		// Validate the form
		if(!$this->validate() && $wfTransType === 'Active') {
			if(Director::is_ajax()) {
				// Special case for legacy Validator.js implementation (assumes eval'ed javascript collected through FormResponse)
				if($this->validator->getJavascriptValidationHandler() == 'prototype') {
					return FormResponse::respond();
				}
			}
			$result = $this->getValidationErrorResponse();
			if ($result) {
				return $result;
			}
		}

		// First, try a handler method on the controller (has been checked for allowed_actions above already)
		if($this->controller->hasMethod($funcName)) {
			return $this->controller->$funcName($vars, $this, $request);
		// Otherwise, try a handler method on the form object.
		} elseif($this->hasMethod($funcName)) {
			return $this->$funcName($vars, $this, $request);
		}
	
		return $this->httpError(404);
	}
}