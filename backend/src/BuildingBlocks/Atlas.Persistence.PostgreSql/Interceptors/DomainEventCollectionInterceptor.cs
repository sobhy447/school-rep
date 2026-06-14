using System.Collections.Concurrent;
using System.Reflection;
using Atlas.BuildingBlocks.Domain.Events;
using Atlas.SharedKernel.Events;
using Atlas.SharedKernel.Primitives;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;

namespace Atlas.Persistence.PostgreSql.Interceptors;

public sealed class DomainEventCollectionInterceptor(IDomainEventDispatcher dispatcher) : SaveChangesInterceptor
{
    private static readonly ConcurrentDictionary<Type, AggregateAccessor?> AccessorCache = new();

    private readonly List<IDomainEvent> _collectedEvents = [];

    public override InterceptionResult<int> SavingChanges(
        DbContextEventData eventData,
        InterceptionResult<int> result)
    {
        if (eventData.Context is not null)
        {
            Collect(eventData.Context);
        }

        return base.SavingChanges(eventData, result);
    }

    public override ValueTask<InterceptionResult<int>> SavingChangesAsync(
        DbContextEventData eventData,
        InterceptionResult<int> result,
        CancellationToken cancellationToken = default)
    {
        if (eventData.Context is not null)
        {
            Collect(eventData.Context);
        }

        return base.SavingChangesAsync(eventData, result, cancellationToken);
    }

    public override int SavedChanges(SaveChangesCompletedEventData eventData, int result)
    {
        DispatchAsync(CancellationToken.None).GetAwaiter().GetResult();
        return base.SavedChanges(eventData, result);
    }

    public override async ValueTask<int> SavedChangesAsync(
        SaveChangesCompletedEventData eventData,
        int result,
        CancellationToken cancellationToken = default)
    {
        await DispatchAsync(cancellationToken);
        return await base.SavedChangesAsync(eventData, result, cancellationToken);
    }

    private void Collect(DbContext context)
    {
        foreach (var entry in context.ChangeTracker.Entries())
        {
            AggregateAccessor? accessor = AccessorCache.GetOrAdd(entry.Entity.GetType(), AggregateAccessor.Create);
            if (accessor is null)
            {
                continue;
            }

            IReadOnlyCollection<IDomainEvent> events = accessor.GetEvents(entry.Entity);
            if (events.Count == 0)
            {
                continue;
            }

            _collectedEvents.AddRange(events);
            accessor.Clear(entry.Entity);
        }
    }

    private async Task DispatchAsync(CancellationToken cancellationToken)
    {
        if (_collectedEvents.Count == 0)
        {
            return;
        }

        IDomainEvent[] events = _collectedEvents.ToArray();
        _collectedEvents.Clear();

        await dispatcher.DispatchAsync(events, cancellationToken);
    }

    private sealed class AggregateAccessor
    {
        private readonly Func<object, IReadOnlyCollection<IDomainEvent>> _getEvents;
        private readonly Action<object> _clear;

        private AggregateAccessor(
            Func<object, IReadOnlyCollection<IDomainEvent>> getEvents,
            Action<object> clear)
        {
            _getEvents = getEvents;
            _clear = clear;
        }

        public IReadOnlyCollection<IDomainEvent> GetEvents(object entity) => _getEvents(entity);

        public void Clear(object entity) => _clear(entity);

        public static AggregateAccessor? Create(Type type)
        {
            if (!IsAggregateRoot(type))
            {
                return null;
            }

            PropertyInfo property = type.GetProperty(
                nameof(AggregateRoot<object>.DomainEvents),
                BindingFlags.Instance | BindingFlags.Public)!;

            MethodInfo clearMethod = type.GetMethod(
                nameof(AggregateRoot<object>.ClearDomainEvents),
                BindingFlags.Instance | BindingFlags.Public)!;

            return new AggregateAccessor(
                entity => (IReadOnlyCollection<IDomainEvent>)property.GetValue(entity)!,
                entity => clearMethod.Invoke(entity, null));
        }

        private static bool IsAggregateRoot(Type type)
        {
            for (Type? current = type.BaseType; current is not null; current = current.BaseType)
            {
                if (current.IsGenericType && current.GetGenericTypeDefinition() == typeof(AggregateRoot<>))
                {
                    return true;
                }
            }

            return false;
        }
    }
}
