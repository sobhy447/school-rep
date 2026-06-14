namespace Atlas.Persistence.PostgreSql.Abstractions;

public interface IAuditableEntity
{
    DateTime CreatedAtUtc { get; }

    DateTime? UpdatedAtUtc { get; }

    void SetCreated(DateTime timestampUtc);

    void SetModified(DateTime timestampUtc);
}
