# Session Handling

Pinch does not use PHP's built-in session handling. Instead, it uses its
own session handling mechanism, modeled after the ones implemented by the Laravel
and Symfony Frameworks, as native session handling relies on global state and PHP
manipulating response cookies. This does not play well with PSR-7 request/response
handling, not to mention writing testable code more difficult.

Our session handling revolves around two classes: `SessionManager` and `SessionData`:
The `SessionManager` class is responsible for managing the sessions lifecycle, e.g.
starting, saving, and destroying sessions. It is a stateful service class that holds
a reference to the current `SessionId` and `SessionData`, and interacts with the
configured `SessionHandler`.

The `SessionData` class is a key-value store for session data. As part of enabling
the user session, the `SessionData` instance is attached to the request object as
an attribute. It is not set on the App container by default. Consuming code
should interact with the `SessionData` instance by retrieving it from the request
object, and setting and getting values from it. It is also possible to get the
`SessionData` by directly from the `SessionManager` by resolving the later class
from the container. Any values added to the `SessionData` instance must be
serializable -- preferably explicitly with the PHP **serialize() and **unserialize()
magic methods.
