namespace Atlas.Persistence.PostgreSql.Abstractions;

public interface ISoftDeletableEntity
{
    bool IsDeleted { get; }

    DateTime? DeletedAtUtc { get; }

    void MarkDeleted(DateTime timestampUtc);
}
