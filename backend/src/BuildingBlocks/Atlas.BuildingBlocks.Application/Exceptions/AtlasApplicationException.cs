namespace Atlas.BuildingBlocks.Application.Exceptions;

public abstract class AtlasApplicationException : Exception
{
    protected AtlasApplicationException(string message, Exception? innerException = null)
        : base(message, innerException)
    {
    }
}
