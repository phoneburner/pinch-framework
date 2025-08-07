# Message Bus

> Note: the Message Bus component replaces the "job, queue, and worker" concepts from the legacy
> system.

The Message Bus sends messages from one system component to another,
allowing that component to do something with the message. Once a message
passes through the message bus, it is returned back to the original dispatcher,
wrapped in an envelope containing metadata about the message's processing. The
"message bus pattern" is a more modern and flexible way to handle both synchronous
and asynchronous processing.

With the message bus, client code could dispatch some kind of UserNotification object,
without having to be concerned with resolving the right service(s), whether to send
it as a Slack message or email, or whether the message should be sent synchronously
or asynchronously. The message bus will handle all of that.

This centers around two main types of classes:

1. _Messages_ - These are the classes that represent the messages that are
   sent between components. These will usually be simple domain objects that hold
   some kind of data. The one requirement for these classes is that they must be
   serializable.

2. _Handlers_ - These are the classes that are ultimately called when a message
   is dispatched. They are responsible for processing the message and performing
   whatever tasks are required. Message handlers must be invokable classes, and
   should declare the [#MessageHandler] attribute on the class.

## Differences from Event Dispatcher

On the surface, the Message Bus and Event Dispatcher are seem similar. Both consume
a (usually lightweight, DTO-like) object, and pass it to a series of pre-configured
listener/handler objects, that can do things.

Note that it is safe to dispatch objects that may not have any listeners configured; however,
the message bus will throw an exception if a message is dispatched without a handler.

- The Event Dispatcher has the intention of the application responding to _when_ something happens
- The Message Bus has the intention of making something specific happen, without needing
  to know all the details of _how_ it happens in the current context.

- The mapping of events to event listeners in the Event Dispatcher is exact by
  the event class name, and does not take inheritance into account.
- The routing of messages to message handlers in the Message Bus is more flexible,
  and can be configured to handle messages based on the message class and its
  parent classes/interfaces.

The two components are used together: for example, when a new SlackNotificationMessage
is dispatched on the message bus, events will be dispatched on the event dispatcher
when the message is received, processed, and complete/failed.
