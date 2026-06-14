using Atlas.SharedKernel.Results;
using MediatR;
using Microsoft.Extensions.Logging;

namespace Atlas.BuildingBlocks.Application.Behaviors;

public sealed class LoggingBehavior<TRequest, TResponse>(
    ILogger<LoggingBehavior<TRequest, TResponse>> logger)
    : IPipelineBehavior<TRequest, TResponse>
    where TRequest : notnull
    where TResponse : Result
{
    public async Task<TResponse> Handle(
        TRequest request,
        RequestHandlerDelegate<TResponse> next,
        CancellationToken cancellationToken)
    {
        var requestName = typeof(TRequest).Name;

        logger.LogInformation("Processing request {RequestName}", requestName);

        TResponse result = await next();

        if (result.IsSuccess)
        {
            logger.LogInformation("Completed request {RequestName}", requestName);
        }
        else
        {
            logger.LogWarning(
                "Completed request {RequestName} with error {ErrorCode}: {ErrorDescription}",
                requestName,
                result.Error.Code,
                result.Error.Description);
        }

        return result;
    }
}
