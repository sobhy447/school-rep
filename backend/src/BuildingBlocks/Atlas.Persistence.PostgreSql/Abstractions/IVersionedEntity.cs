namespace Atlas.Persistence.PostgreSql.Abstractions;

public interface IVersionedEntity
{
    int EntityVersion { get; }

    void AdvanceVersion();
}
