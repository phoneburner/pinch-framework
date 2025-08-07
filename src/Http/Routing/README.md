# Routing

Application routes should be defined in classes that implement the
`\PhoneBurner\Pinch\Framework\Http\Routing\RouteProvider` interface. That interface
defines an invokable class that returns an array of PhoneBurner\Pinch\Framework\Http\Routing\Definition\Definition
instances. The DQL for defining routes is as follows is the same as the full Salt
codebase. The `RouteProvider` should then be registered with the application
in the `config/routes.php` file.

the implementation of the `getRoutes()` method, which should return an array of routes. Each route should be an associative array with the following keys:
