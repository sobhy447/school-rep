namespace Atlas.BuildingBlocks.Application.Exceptions;

public sealed class ConcurrencyConflictException : AtlasApplicationException
{
    public ConcurrencyConflictException(string message, Exception? innerException = null)
        : base(message, innerException)
    {
    }
}
