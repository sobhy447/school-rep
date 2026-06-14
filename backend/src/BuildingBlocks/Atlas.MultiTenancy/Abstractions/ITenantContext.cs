namespace Atlas.MultiTenancy.Abstractions;

public interface ITenantContext
{
    long TenantId { get; }

    bool IsResolved { get; }

    bool TryGetTenantId(out long tenantId);
}
