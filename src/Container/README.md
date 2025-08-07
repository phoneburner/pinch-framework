# Service Container

### PSR-11 Service Container

Service providers have two required methods: `bind()` and `register()`. The `bind()` method
is used to return a map of class names to implementations, e.g. binding an interface name
to a concrete class, defined elsewhere in the provider.. The `register()` method
is used to set up the definitions of the services themselves as \Closure objects.

## Differences from Legacy Salt Container

- Provider registration can be deferred until a service defined by that provider is resolved.
- `MutableContainer` interface is now split into

### Deferring Provider Registration

Sometimes we don't want to have a service provider register its services unless
it is necessary. For example, registering HTTP-only services while executing a
console command is useless overhead. Similarly, registering services that only
apply to certain drivers/connections that are not used by the current runtime are
also unnecessary. To address this, we can use the `DeferredServiceProvider` class,
and define a list of services that the provider can register in the `provides()`
method.

**Important: you must include all services registered by `register()` and bound by
`bind()` in the service provider in the `provides()` method**

The first time we boot the container, we create a map of deferred services to their
provider, and then register the provider when the service is requested. We also
register all the services provided by a deferred provider before a deferred service
is manually set on the container. This prevents accidentally overwriting a service
definition with a deferred provider.

## Helper Containers

The PSR-11 container interface pops up in a number of contexts, beyond the most
common usages as a general application service container. Pinch comes with
a few generic, reusable helper container classes that are useful in
a number of contexts.

### ObjectContainer

`\PhoneBurner\Pinch\Container\ObjectContainer` is a simple
interface defining a container that stores objects of a single type. It is countable,
iterable, and array-accessible. It currently has to variants, though in both cases,
the "genericness" of the container is defined by type annotations. These classes
rely on the user to verify that the objects passed into in the container are of
the correct type.

- `MutableObjectContainer`: An extendable version that allows objects to be added and removed.
- `ImmutableObjectContainer`: An immutable version that is also marked as final and readonly.
