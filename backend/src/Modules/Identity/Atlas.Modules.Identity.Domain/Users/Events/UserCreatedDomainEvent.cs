using Atlas.SharedKernel.Events;

namespace Atlas.Modules.Identity.Domain.Users.Events;

public sealed record UserCreatedDomainEvent(long TenantId, string Username) : DomainEvent;
