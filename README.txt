## Thoughts on the original code
It was terrible. 
- It was a terrible attempt to adapt and implement Repository pattern.
- Did not follow SOLID principle at all
- Low-level code quality (Terrible variable names, incorrect logics, unaccessible code blocks, etc.)
- Did not make use of classes to make the code more modular and reusable
- No request validation implemented
- No database error handling
- No tests implemented

Repositories mediates the business logic layer and the data source layer of the application.
The data source layer clearly refers to the Model & Eloquent.
How about the business logic layer? Yes, we still need to add a new layer to the application, which in my case, I call it a Service layer.
Why do we need a Service layer?
We need a service layer to handle all our business logics because repositories are not to contain logic at all. Its responsibility should only be transmitting and retrieving data from the data source (model/eloqquent).
Therefore, all the business logics should be put inside the services.
So the correct flow would be 
Request -> Controllers -> Services -> Repositories -> Models
And vice versa.

Other issues are not as grave as this one as those can be due to inexperience and weak foundation in PHP and/or Laravel in general. Attempting to adapt a pattern when your foundation is still shallow is a serious mistake that would result to a codebase much like this one (The original, unrefactored).

## Note:
Since there is no way to actually run the code for manual testing, there might be minor issues such as incorrect variable/method name or incorrect attribute calls that I missed during the refactoring.
Also, I only refactored about 70% of the code as I see that it is already more than enough to demonstrate my skills.
Tests can be found in `app/tests/Unit/*` directory.

Thanks!

