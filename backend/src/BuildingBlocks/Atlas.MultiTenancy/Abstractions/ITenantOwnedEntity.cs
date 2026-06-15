namespace Atlas.MultiTenancy.Abstractions;

public interface ITenantOwnedEntity
{
    long TenantId { get; }
}
