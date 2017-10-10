<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

class RuteController extends Controller
{
    public function actionIndex()
    {
        $rute = ['avaliable' => array_keys($this->getAppRoutes())];
        print_r($rute);
    }

    public function getAppRoutes($module = null)
    {
        if ($module === null) {
            $module = Yii::$app;
        } elseif (is_string($module)) {
            $module = Yii::$app->getModule($module);
        }
        $key = [__METHOD__, $module->getUniqueId()];
        $result = [];
        $this->getRouteRecrusive($module, $result);

        return $result;
    }

    protected function getRouteRecrusive($module, &$result)
    {
        foreach ($module->getModules() as $id => $child) {
            if (($child = $module->getModule($id)) !== null) {
                $this->getRouteRecrusive($child, $result);
            }
        }

        foreach ($module->controllerMap as $id => $type) {
            $this->getControllerActions($type, $id, $module, $result);
        }

        $namespace = trim($module->controllerNamespace, '\\') . '\\';
        $this->getControllerFiles($module, $namespace, '', $result);
        $all = '/' . ltrim($module->uniqueId . '/*', '/');
        $result[$all] = $all;
    }

    protected function getControllerFiles($module, $namespace, $prefix, &$result)
    {
        $path = Yii::getAlias('@' . str_replace('\\', '/', $namespace), false);
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($path . '/' . $file) && preg_match('%^[a-z0-9_/]+$%i', $file . '/')) {
                $this->getControllerFiles($module, $namespace . $file . '\\', $prefix . $file . '/', $result);
            } elseif (strcmp(substr($file, -14), 'Controller.php') === 0) {
                $baseName = substr(basename($file), 0, -14);
                $name = strtolower(preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $baseName));
                $id = ltrim(str_replace(' ', '-', $name), '-');
                $className = $namespace . $baseName . 'Controller';
                if (strpos($className, '-') === false && class_exists($className) && is_subclass_of($className, 'yii\base\Controller')) {
                    $this->getControllerActions($className, $prefix . $id, $module, $result);
                }
            }
        }
    }

    protected function getControllerActions($type, $id, $module, &$result)
    {
        $controller = Yii::createObject($type, [$id, $module]);
        $this->getActionRoutes($controller, $result);
        $all = "/{$controller->uniqueId}/*";
        $result[$all] = $all;
    }

    protected function getActionRoutes($controller, &$result)
    {
        $prefix = '/' . $controller->uniqueId . '/';
        foreach ($controller->actions() as $id => $value) {
            $result[$prefix . $id] = $prefix . $id;
        }
        $class = new \ReflectionClass($controller);
        foreach ($class->getMethods() as $method) {
            $name = $method->getName();
            if ($method->isPublic() && !$method->isStatic() && strpos($name, 'action') === 0 && $name !== 'actions') {
                $name = strtolower(preg_replace('/(?<![A-Z])[A-Z]/', ' \0', substr($name, 6)));
                $id = $prefix . ltrim(str_replace(' ', '-', $name), '-');
                $result[$id] = $id;
            }
        }
    }
}
