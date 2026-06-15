using Atlas.SharedKernel.Results;
using Microsoft.AspNetCore.Http;

namespace Atlas.Modules.Identity.Endpoints.Internal;

internal static class ApiResults
{
    public static IResult Match<TValue>(this Result<TValue> result, Func<TValue, IResult> onSuccess) =>
        result.IsSuccess ? onSuccess(result.Value) : Problem(result.Error);

    public static IResult Problem(Error error)
    {
        int statusCode = error.Type switch
        {
            ErrorType.Validation => StatusCodes.Status400BadRequest,
            ErrorType.Unauthorized => StatusCodes.Status401Unauthorized,
            ErrorType.Forbidden => StatusCodes.Status403Forbidden,
            ErrorType.NotFound => StatusCodes.Status404NotFound,
            ErrorType.Conflict => StatusCodes.Status409Conflict,
            _ => StatusCodes.Status500InternalServerError
        };

        if (error is ValidationError validationError)
        {
            Dictionary<string, string[]> errors = validationError.Errors
                .GroupBy(item => item.Code)
                .ToDictionary(group => group.Key, group => group.Select(item => item.Description).ToArray());

            return Results.ValidationProblem(errors, statusCode: statusCode);
        }

        return Results.Problem(detail: error.Description, statusCode: statusCode, title: error.Code);
    }
}
