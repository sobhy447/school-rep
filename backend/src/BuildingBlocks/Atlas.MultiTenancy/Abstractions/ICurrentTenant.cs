using Atlas.MultiTenancy.Context;

namespace Atlas.MultiTenancy.Abstractions;

public interface ICurrentTenant
{
    TenantScope Change(long tenantId);
}
