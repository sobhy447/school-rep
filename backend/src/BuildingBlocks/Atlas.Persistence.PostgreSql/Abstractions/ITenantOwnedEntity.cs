namespace Atlas.Persistence.PostgreSql.Abstractions;

public interface ITenantOwnedEntity
{
    long TenantId { get; }

    void SetTenant(long tenantId);
}
