using Atlas.MultiTenancy.Abstractions;
using Atlas.MultiTenancy.Exceptions;

namespace Atlas.MultiTenancy.Context;

public sealed class TenantContextAccessor : ITenantContext, ICurrentTenant
{
    private static readonly AsyncLocal<long?> CurrentTenantId = new();

    public bool IsResolved => CurrentTenantId.Value.HasValue;

    public long TenantId =>
        CurrentTenantId.Value
        ?? throw new TenantResolutionException(
            "No tenant has been resolved for the current execution context.");

    public bool TryGetTenantId(out long tenantId)
    {
        if (CurrentTenantId.Value is { } value)
        {
            tenantId = value;
            return true;
        }

        tenantId = default;
        return false;
    }

    public TenantScope Change(long tenantId)
    {
        if (tenantId <= 0)
        {
            throw new TenantResolutionException(
                $"Tenant identifier must be a positive value. Received: {tenantId}.");
        }

        long? previous = CurrentTenantId.Value;
        CurrentTenantId.Value = tenantId;

        return new TenantScope(() => CurrentTenantId.Value = previous);
    }
}
