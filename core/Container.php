<?php
// core/Container.php

class Container
{
    private array $bindings = [];
    private array $instances = [];

    /**
     * Registra una definición de servicio / dependencia
     */
    public function set(string $id, callable $resolver): void
    {
        $this->bindings[$id] = $resolver;
        unset($this->instances[$id]); // Limpiar instancia previa si existiera
    }

    /**
     * Registra una definición de instancia única (Singleton)
     */
    public function singleton(string $id, callable $resolver): void
    {
        $this->bindings[$id] = function ($container) use ($resolver) {
            static $instance;
            if ($instance === null) {
                $instance = $resolver($container);
            }
            return $instance;
        };
    }

    /**
     * Obtiene y resuelve una dependencia
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $resolver = $this->bindings[$id];
            return $resolver($this);
        }

        // Resolución automática mediante Reflexión (Autowiring)
        return $this->autowire($id);
    }

    /**
     * Resolución automática inspeccionando el constructor de la clase
     */
    private function autowire(string $class): mixed
    {
        if (!class_exists($class)) {
            throw new Exception("No se puede resolver '$class': la clase no existe en el sistema.");
        }

        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new Exception("La clase '$class' no es instanciable (puede ser una interfaz o clase abstracta sin mapeo en el contenedor).");
        }

        $constructor = $reflector->getConstructor();

        // Si no tiene constructor, instanciar directamente
        if ($constructor === null) {
            return new $class();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            // Si el parámetro no está tipado o es un tipo nativo (string, int, etc.)
            if ($type === null || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new Exception("No se puede auto-resolver el parámetro '{$parameter->getName()}' en el constructor de '$class'.");
            }

            // Resolver recursivamente el tipo de clase/interfaz inyectada
            $dependencies[] = $this->get($type->getName());
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
