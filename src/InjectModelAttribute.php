<?php

namespace RainPlus\EloquentInjectAttribute;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use WeakMap;

/**
 * This class is used to inject a dynamic attribute to an Eloquent model without having to saving it to database.
 */
class InjectModelAttribute
{
    protected ReflectionClass $reflection;

    protected array $attributes = [];

    protected array $defaultValues = [];

    protected array $saveCallbacks = [];

    protected WeakMap $valuesBeforeSaving;

    /**
     * @param string $modelClass The class that needs to be injected.
     * @throws ReflectionException
     */
    public function __construct(string $modelClass) {
        $this->valuesBeforeSaving = new WeakMap();
        $this->reflection = new ReflectionClass($modelClass);
        if (!$this->reflection->isSubclassOf(Model::class)) throw new InvalidArgumentException("Class [$modelClass] does not extend Eloquent class.");
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_STATIC);
        foreach ($methods as $method) {
            if (
                $method->name === 'retrieved' ||
                $method->name === 'created'
            ) {
                call_user_func($modelClass . '::' . $method->name, function(Model $model) {
                    foreach ($this->defaultValues as $name => $value) {
                        if (is_callable($value)) {
                            $model->{$name} = call_user_func($value, $model);
                        } else {
                            $model->{$name} = $value;
                        }
                        $originalProp = $this->reflection->getProperty('original');
                        $originalProp->setValue($model, array_merge($originalProp->getValue($model) ?: [], [
                            $name => $model->{$name}
                        ]));
                    }
                });
            } else if ($method->name === 'saving') {
                call_user_func($modelClass . '::' . $method->name, function(Model $model) {
                    $this->valuesBeforeSaving[$model] = [];
                    $attrProp = $this->reflection->getProperty('attributes');
                    $attrs = $attrProp->getValue($model);
                    foreach ($this->attributes as $attr) {
                        $callback = $this->saveCallbacks[$attr];
                        if (is_callable($callback)) {
                            $callback($model, $attr, $model->{$attr});
                        }
                        $this->valuesBeforeSaving[$model][$attr] = $model->{$attr};
                        Arr::pull($attrs, $attr);
                    }
                    $attrProp->setValue($model, $attrs);
                });
            } else if ($method->name === 'saved') {
                call_user_func($modelClass . '::' . $method->name, function(Model $model) {
                    foreach ($this->attributes as $attr) {
                        $model->{$attr} = $this->valuesBeforeSaving[$model][$attr];
                    }
                    $this->valuesBeforeSaving->offsetUnset($model);
                });
            }
        }
    }

    /**
     * Add a dynamic attribute to the model.
     * @param string $name Attribute name
     * @param mixed|callable|null $defaultValue The default value for the attribute or a callable that receives the model as argument and returns the value.
     * @param callable|null $onSave Callback when saving the model.
     * @return $this
     */
    public function attribute(string $name, mixed $defaultValue = null, callable|null $onSave = null): static {
        $this->attributes[] = $name;
        if ($defaultValue !== null) {
            $this->defaultValues[$name] = $defaultValue;
        }
        if (is_callable($onSave)) {
            $this->saveCallbacks[$name] = $onSave;
        }
        return $this;
    }
}
