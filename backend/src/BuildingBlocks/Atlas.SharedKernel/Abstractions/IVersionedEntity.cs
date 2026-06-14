namespace Atlas.SharedKernel.Abstractions;

public interface IVersionedEntity
{
    int EntityVersion { get; }

    void AdvanceVersion();
}
