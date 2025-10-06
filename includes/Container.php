<?php
/**
 * Dependency Injection Container
 * Replaces static method calls with proper dependency injection
 * Standalone implementation without external dependencies
 */

class Container {

    private array $services = [];
    private array $instances = [];
    private array $factories = [];

    public function __construct() {
        $this->registerCoreServices();
    }

    /**
     * Register a service with the container
     */
    public function register(string $id, callable $factory, bool $singleton = true): void {
        $this->factories[$id] = $factory;
        if (!$singleton) {
            unset($this->instances[$id]);
        }
    }

    /**
     * Register an existing instance
     */
    public function instance(string $id, object $instance): void {
        $this->instances[$id] = $instance;
    }

    /**
     * Check if service exists
     */
    public function has(string $id): bool {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    /**
     * Get service instance
     */
    public function get(string $id) {
        // Return existing instance if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Create new instance using factory
        if (isset($this->factories[$id])) {
            $instance = $this->factories[$id]($this);
            $this->instances[$id] = $instance;
            return $instance;
        }

        throw new \Exception("Service '{$id}' not found in container");
    }

    /**
     * Register core VivalaTable services
     */
    private function registerCoreServices(): void {
        // Database Connection
        $this->register('database.connection', function($container) {
            return new VT_Database_Connection();
        });

        // Database Query Builder
        $this->register('database.query', function($container) {
            return new VT_Database_QueryBuilder(
                $container->get('database.connection')
            );
        });

        // HTTP Request
        $this->register('http.request', function($container) {
            return VT_Http_Request::createFromGlobals();
        });

        // HTTP Response Factory
        $this->register('http.response', function($container) {
            return new VT_Http_Response();
        }, false); // Not singleton - new response each time

        // Authentication Services
        $this->register('auth.user_repository', function($container) {
            return new VT_Auth_UserRepository(
                $container->get('database.connection')
            );
        });

        $this->register('auth.service', function($container) {
            return new VT_Auth_AuthenticationService(
                $container->get('auth.user_repository')
            );
        });

        // Legacy compatibility - keep existing VT_* classes working
        $this->register('legacy.database', function($container) {
            return VT_Database::getInstance();
        });

        $this->register('legacy.auth', function($container) {
            return new VT_Auth();
        });

        $this->register('legacy.http', function($container) {
            return new VT_Http();
        });

        // Validation Services
        $this->register('validation.sanitizer', function($container) {
            return new VT_Validation_InputSanitizer();
        });

        $this->register('validation.validator', function($container) {
            return new VT_Validation_ValidatorService(
                $container->get('validation.sanitizer')
            );
        });

        // Security Service
        $this->register('security.service', function($container) {
            return new VT_Security_SecurityService();
        });

        // Invitation Service
        $this->register('invitation.service', function($container) {
            return new VT_Invitation_Service();
        });
    }

    /**
     * Create VT_* compatibility layer for gradual migration
     */
    public function createCompatibilityLayer(): void {
        // Make container globally accessible for transition period
        $GLOBALS['vt_container'] = $this;

        // Create compatibility functions
        if (!function_exists('vt_container')) {
            function vt_container(): Container {
                return $GLOBALS['vt_container'];
            }
        }

        if (!function_exists('vt_service')) {
            function vt_service(string $id) {
                return $GLOBALS['vt_container']->get($id);
            }
        }
    }
}

/**
 * Container Exception Classes
 */
class ContainerException extends \Exception {}

class NotFoundException extends ContainerException {}