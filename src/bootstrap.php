<?php
declare(strict_types=1);

use App\Database\Database;
use App\Http\Controller\AuthController;
use App\Http\Controller\EventController;
use App\Http\Controller\CommunityController;
use App\Http\Controller\CommunityApiController;
use App\Http\Controller\ConversationController;
use App\Http\Controller\ConversationApiController;
use App\Http\Controller\InvitationApiController;
use App\Http\Request;
use App\Services\EventService;
use App\Services\CommunityService;
use App\Services\ConversationService;
use App\Services\CircleService;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\SanitizerService;
use App\Services\ValidatorService;
use App\Services\InvitationService;
use App\Services\AuthorizationService;
use PHPMailer\PHPMailer\PHPMailer;


require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../legacy/includes/includes/class-security.php';
require_once __DIR__ . '/../legacy/includes/includes/Security/SecurityService.php';
require_once __DIR__ . '/../legacy/includes/includes/class-invitation-service.php';

if (!defined('VT_VERSION')) {
    define('VT_VERSION', '2.0-dev');
}

/**
 * Very small service container for modern code paths.
 *
 * This intentionally mirrors the legacy helper functions (`vt_service`,
 * `vt_container`) so templates and controllers can stay agnostic while the
 * migration to namespaced classes progresses.
 */
final class VTContainer
{
    /** @var array<string, callable(self):mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Register a service factory.
     *
     * @param string $id Identifier like "event.service".
     * @param callable(self):mixed $factory Factory that returns the service instance.
     * @param bool $shared Whether the instance should be cached (default true).
     */
    public function register(string $id, callable $factory, bool $shared = true): void
    {
        $this->factories[$id] = [$factory, $shared];
        if (!$shared) {
            unset($this->instances[$id]);
        }
    }

    /**
     * Resolve a service by id.
     *
     * @template T
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException(sprintf('Service "%s" not registered.', $id));
        }

        [$factory, $shared] = $this->factories[$id];
        $instance = $factory($this);

        if ($shared) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }
}

if (!function_exists('vt_container')) {
    /**
     * Retrieve the global container instance, creating it on first use.
     */
    function vt_container(): VTContainer
    {
        static $container = null;

        if ($container === null) {
            $container = new VTContainer();

            $container->register('config.database', static function (): array {
                $path = __DIR__ . '/../config/database.php';
                if (!is_file($path)) {
                    throw new RuntimeException('Missing config/database.php. Copy database.php.sample and update credentials.');
                }

                $config = require $path;
                if (!is_array($config)) {
                    throw new RuntimeException('config/database.php must return an array.');
                }

                return $config;
            });

            $container->register('database.connection', static function (VTContainer $c): Database {
                return new Database($c->get('config.database'));
            });

            $container->register('event.service', static function (VTContainer $c): EventService {
                return new EventService($c->get('database.connection'));
            });

            $container->register('community.service', static function (VTContainer $c): CommunityService {
                return new CommunityService($c->get('database.connection'));
            });

            $container->register('conversation.service', static function (VTContainer $c): ConversationService {
                return new ConversationService($c->get('database.connection'));
            });

            $container->register('circle.service', static function (VTContainer $c): CircleService {
                return new CircleService($c->get('database.connection'));
            });

            $container->register('sanitizer.service', static function (): SanitizerService {
                return new SanitizerService();
            });

            $container->register('validator.service', static function (VTContainer $c): ValidatorService {
                return new ValidatorService($c->get('sanitizer.service'));
            });

            $container->register('authorization.service', static function (VTContainer $c): AuthorizationService {
                return new AuthorizationService($c->get('database.connection'));
            });

            $container->register('auth.service', static function (VTContainer $c): AuthService {
                return new AuthService($c->get('database.connection'), $c->get('mail.service'));
            });

            $container->register('mail.service', static function (): MailService {
                $mailer = new PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = $_ENV['SMTP_HOST'] ?? 'localhost';
                $mailer->Port = (int)($_ENV['SMTP_PORT'] ?? 587);
                $mailer->SMTPAuth = !empty($_ENV['SMTP_USERNAME']);
                if ($mailer->SMTPAuth) {
                    $mailer->Username = $_ENV['SMTP_USERNAME'];
                    $mailer->Password = $_ENV['SMTP_PASSWORD'];
                }
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

                $fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@vivalatable.com';
                $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'VivalaTable';

                return new MailService($mailer, $fromEmail, $fromName);
            });

            $container->register('security.service', static function (): \VT_Security_SecurityService {
                return new \VT_Security_SecurityService();
            });

            $container->register('invitation.service', static function (): \VT_Invitation_Service {
                return new \VT_Invitation_Service();
            });

            $container->register('invitation.manager', static function (VTContainer $c): InvitationService {
                return new InvitationService(
                    $c->get('database.connection'),
                    $c->get('auth.service')
                );
            });

            $container->register('controller.auth', static function (VTContainer $c): AuthController {
                return new AuthController($c->get('auth.service'), $c->get('validator.service'));
            }, false);

            $container->register('controller.events', static function (VTContainer $c): EventController {
                return new EventController(
                    $c->get('event.service'),
                    $c->get('auth.service'),
                    $c->get('validator.service')
                );
            }, false);

            $container->register('controller.communities', static function (VTContainer $c): CommunityController {
                return new CommunityController(
                    $c->get('community.service'),
                    $c->get('circle.service'),
                    $c->get('auth.service'),
                    $c->get('authorization.service'),
                    $c->get('validator.service')
                );
            }, false);

            $container->register('controller.communities.api', static function (VTContainer $c): CommunityApiController {
                return new CommunityApiController(
                    $c->get('community.service'),
                    $c->get('auth.service'),
                    $c->get('authorization.service')
                );
            }, false);

            $container->register('controller.conversations', static function (VTContainer $c): ConversationController {
                return new ConversationController(
                    $c->get('conversation.service'),
                    $c->get('circle.service'),
                    $c->get('auth.service'),
                    $c->get('authorization.service'),
                    $c->get('validator.service')
                );
            }, false);

            $container->register('controller.conversations.api', static function (VTContainer $c): ConversationApiController {
                return new ConversationApiController(
                    $c->get('conversation.service'),
                    $c->get('circle.service'),
                    $c->get('auth.service')
                );
            }, false);

            $container->register('controller.invitations', static function (VTContainer $c): InvitationApiController {
                return new InvitationApiController(
                    $c->get('database.connection'),
                    $c->get('auth.service'),
                    $c->get('invitation.manager')
                );
            }, false);

            $container->register('http.request', static function (): Request {
                return Request::fromGlobals();
            }, false);
        }

        return $container;
    }
}

if (!function_exists('vt_service')) {
    /**
     * Convenience accessor for services during the migration.
     *
     * @param string $id
     * @return mixed
     */
    function vt_service(string $id)
    {
        return vt_container()->get($id);
    }
}

/**
 * Legacy compatibility shim: VT_Text
 * Keeps old partials (like templates/partials/entity-card.php) working.
 */
if (!class_exists('VT_Text')) {
    final class VT_Text {
        public static function esc(string $text): string {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
        public static function plain(string $text): string {
            return trim(strip_tags($text));
        }
        public static function truncate(string $text, int $limit = 120, string $ellipsis = '…'): string {
            return self::truncate_chars($text, $limit, $ellipsis);
        }
        public static function truncate_chars(string $text, int $limit = 120, string $ellipsis = '…'): string {
            $t = self::plain($text);
            if (mb_strlen($t) <= $limit) return $t;
            return rtrim(mb_substr($t, 0, $limit)) . $ellipsis;
        }
        public static function truncate_words(string $text, int $limit = 25, string $ellipsis = '…'): string {
            $t = preg_replace('/\s+/u', ' ', self::plain($text));
            $words = $t === '' ? [] : explode(' ', $t);
            if (count($words) <= $limit) return $t;
            return implode(' ', array_slice($words, 0, $limit)) . $ellipsis;
        }
        public static function excerpt(string $text, int $words = 30, string $ellipsis = '…'): string {
            return self::truncate_words($text, $words, $ellipsis);
        }
    }
}
