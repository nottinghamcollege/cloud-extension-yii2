<?php

namespace craft\cloud\controllers;

use craft\web\Controller;
use craft\web\Response;

class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function actionIndex(): Response
    {
        return $this->response;
    }
}
