using Atlas.SharedKernel.Results;

namespace Atlas.BuildingBlocks.Application.Exceptions;

public sealed class ValidationException : AtlasApplicationException
{
    public ValidationException(IReadOnlyCollection<Error> errors)
        : base("One or more validation failures have occurred.") =>
        Errors = errors;

    public IReadOnlyCollection<Error> Errors { get; }
}
