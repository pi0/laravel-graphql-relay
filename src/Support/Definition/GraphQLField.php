<?php

namespace Nuwave\Relay\Support\Definition;

use Illuminate\Support\Fluent;
use Illuminate\Support\DefinitionsFluent;
use Nuwave\Relay\Schema\GraphQL;
use Nuwave\Relay\Traits\GlobalIdTrait;
use Nuwave\Relay\Support\ValidationError;

class GraphQLField extends Fluent
{
    use GlobalIdTrait;

    /**
     * The container instance of GraphQL.
     *
     * @var \Laravel\Lumen\Application|mixed
     */
    protected $graphQL;

    /**
     * GraphQLType constructor.
     *
     */
    public function __construct()
    {
        parent::__construct();

        $this->graphQL = app('graphql');
    }

    /**
     * Arguments this field accepts.
     *
     * @return array
     */
    public function args()
    {
        return [];
    }

    /**
     * Field attributes.
     *
     * @return array
     */
    public function attributes()
    {
        return [];
    }

    /**
     * The field type.
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public function type()
    {
        return null;
    }

    /**
     * Rules to apply to mutation.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Get the attributes of the field.
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = array_merge($this->attributes, [
            'args' => $this->args()
        ], $this->attributes());

        $attributes['type'] = $this->type();

        $attributes['resolve'] = $this->getResolver();

        return $attributes;
    }

    /**
     * Get rules for mutation.
     *
     * @return array
     */
    public function getRules()
    {
        $arguments = func_get_args();

        return collect($this->args())
            ->transform(function ($arg, $name) use ($arguments) {
                if (isset($arg['rules'])) {
                    if (is_callable($arg['rules'])) {
                        return call_user_func_array($arg['rules'], $arguments);
                    }
                    return $arg['rules'];
                }
                return null;
            })
            ->merge(call_user_func_array([$this, 'rules'], $arguments))
            ->reject(function ($arg) {
                return is_null($arg);
            })
            ->toArray();
    }

    /**
     * Get the mutation resolver.
     *
     * @return \Closure|null
     */
    protected function getResolver()
    {
        if (!method_exists($this, 'resolve')) {
            return null;
        }

        $resolver = array($this, 'resolve');

        return function () use ($resolver) {
            $arguments = func_get_args();
            $rules = call_user_func_array([$this, 'getRules'], $arguments);

            if (sizeof($rules)) {
                $args = array_get($arguments, 1, []);
                $validator = app('validator')->make($args, $rules);

                if ($validator->fails()) {
                    throw with(new ValidationError('validation'))->setValidator($validator);
                }
            }

            return call_user_func_array($resolver, $arguments);
        };
    }

    /**
     * Convert the Fluent instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $attributes = $this->getAttributes();

        return isset($attributes[$key]) ? $attributes[$key]:null;
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->getAttributes()[$key]);
    }
}
