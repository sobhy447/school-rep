using Atlas.SharedKernel.Results;
using FluentValidation;
using FluentValidation.Results;
using MediatR;

namespace Atlas.BuildingBlocks.Application.Behaviors;

public sealed class ValidationBehavior<TRequest, TResponse>(IEnumerable<IValidator<TRequest>> validators)
    : IPipelineBehavior<TRequest, TResponse>
    where TRequest : notnull
    where TResponse : Result
{
    public async Task<TResponse> Handle(
        TRequest request,
        RequestHandlerDelegate<TResponse> next,
        CancellationToken cancellationToken)
    {
        var validatorList = validators as IReadOnlyList<IValidator<TRequest>> ?? validators.ToList();

        if (validatorList.Count == 0)
        {
            return await next();
        }

        var context = new ValidationContext<TRequest>(request);

        ValidationFailure[] failures = (await Task.WhenAll(
                validatorList.Select(validator => validator.ValidateAsync(context, cancellationToken))))
            .SelectMany(result => result.Errors)
            .Where(failure => failure is not null)
            .ToArray();

        if (failures.Length == 0)
        {
            return await next();
        }

        return CreateValidationResult(CreateValidationError(failures));
    }

    private static ValidationError CreateValidationError(IEnumerable<ValidationFailure> failures) =>
        new(failures
            .Select(failure => Error.Validation(failure.PropertyName, failure.ErrorMessage))
            .ToArray());

    private static TResponse CreateValidationResult(ValidationError validationError)
    {
        if (typeof(TResponse) == typeof(Result))
        {
            return (TResponse)(object)Result.Failure(validationError);
        }

        object validationResult = typeof(Result)
            .GetMethod(nameof(Result.ValidationFailure))!
            .MakeGenericMethod(typeof(TResponse).GenericTypeArguments[0])
            .Invoke(null, [validationError])!;

        return (TResponse)validationResult;
    }
}
