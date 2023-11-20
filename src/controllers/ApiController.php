<?php

namespace craft\cloud\controllers;

use craft\web\Controller;

class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function actionIndex()
    {
    }
}
