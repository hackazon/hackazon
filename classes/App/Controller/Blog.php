<?php
namespace App\Controller;

class Blog extends \App\Page {

	public function action_index(){
		$this->view->subview = 'blog';
		$this->view->message = "Index page";
	}
	
	public function action_post(){
		$this->view->subview = 'blog_post';
		$this->view->message = "Index page";
	}        
}