using System.Reflection;

namespace Atlas.SharedKernel.Primitives;

public abstract class Enumeration<TEnum> : IEquatable<Enumeration<TEnum>>, IComparable<Enumeration<TEnum>>
    where TEnum : Enumeration<TEnum>
{
    private static readonly Lazy<IReadOnlyDictionary<int, TEnum>> EnumerationsById =
        new(() => CreateEnumerations().ToDictionary(item => item.Id));

    private static readonly Lazy<IReadOnlyDictionary<string, TEnum>> EnumerationsByName =
        new(() => CreateEnumerations().ToDictionary(item => item.Name, StringComparer.Ordinal));

    protected Enumeration(int id, string name)
    {
        Id = id;
        Name = name;
    }

    public int Id { get; }

    public string Name { get; }

    public static IReadOnlyCollection<TEnum> GetAll() =>
        EnumerationsById.Value.Values.ToList();

    public static TEnum? FromId(int id) =>
        EnumerationsById.Value.GetValueOrDefault(id);

    public static TEnum? FromName(string name) =>
        EnumerationsByName.Value.GetValueOrDefault(name);

    public int CompareTo(Enumeration<TEnum>? other) =>
        other is null ? 1 : Id.CompareTo(other.Id);

    public bool Equals(Enumeration<TEnum>? other) =>
        other is not null && GetType() == other.GetType() && Id == other.Id;

    public override bool Equals(object? obj) =>
        obj is Enumeration<TEnum> enumeration && Equals(enumeration);

    public override int GetHashCode() => Id.GetHashCode();

    public override string ToString() => Name;

    public static bool operator ==(Enumeration<TEnum>? left, Enumeration<TEnum>? right) =>
        Equals(left, right);

    public static bool operator !=(Enumeration<TEnum>? left, Enumeration<TEnum>? right) =>
        !Equals(left, right);

    private static IEnumerable<TEnum> CreateEnumerations()
    {
        var enumerationType = typeof(TEnum);

        return enumerationType
            .GetFields(BindingFlags.Public | BindingFlags.Static | BindingFlags.FlattenHierarchy)
            .Where(fieldInfo => enumerationType.IsAssignableFrom(fieldInfo.FieldType))
            .Select(fieldInfo => (TEnum)fieldInfo.GetValue(null)!);
    }
}
