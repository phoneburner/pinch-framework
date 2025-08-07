# Application Health Check API

### Health Check

This API is used to check the health of the application. It returns the status
of the application, based on various configured component health checks. This is
based on the draft RCF, ["Health Check Response Format for HTTP APIs"](https://datatracker.ietf.org/doc/html/draft-inadarei-api-health-check).
Since this endpoint's response includes sensitive information, access should be
restricted to authenticated and authorized users.

## Ready Check

This endpoint is used to check if the application is ready to serve traffic.
It returns a 200 OK status code if the application is ready, with a simple text
response. Since no additional checks are performed or reported on, this endpoint
does not need authentication and/or authorization.
