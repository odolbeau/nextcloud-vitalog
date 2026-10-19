<?php

declare(strict_types=1);

// Classes internes du serveur référencées par OCP : nécessaires uniquement pour pouvoir les mocker.

namespace OC\Hooks {
	interface Emitter {
		public function listen($scope, $method, callable $callback);

		public function removeListener($scope = null, $method = null, ?callable $callback = null);
	}
}

namespace OCP\User\Exceptions {
	class UserNotFoundException extends \Exception {
	}
}

namespace OC\User {
	class NoUserException extends \OCP\User\Exceptions\UserNotFoundException {
	}
}
