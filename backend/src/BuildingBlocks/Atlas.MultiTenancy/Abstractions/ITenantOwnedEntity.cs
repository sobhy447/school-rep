namespace Atlas.MultiTenancy.Abstractions;

public interface ITenantOwnedEntity
{
    long TenantId { get; }

    void SetTenant(long tenantId);
}
