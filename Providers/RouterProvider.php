<?php namespace Model\Form\Providers;

use Model\Router\AbstractRouterProvider;

class RouterProvider extends AbstractRouterProvider
{
	public static function getRoutes(): array
	{
		return [
			[
				'pattern' => '/model-form',
				'controller' => 'ModelForm',
			],
		];
	}
}
