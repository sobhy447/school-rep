using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Tenancy.Domain.ValueObjects;

public sealed class LocalizedName : ValueObject
{
    public const int MaxLength = 500;

    private LocalizedName(string arabic, string english)
    {
        Arabic = arabic;
        English = english;
    }

    public string Arabic { get; }

    public string English { get; }

    public static LocalizedName Create(string arabic, string english)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(arabic);
        ArgumentException.ThrowIfNullOrWhiteSpace(english);

        string trimmedArabic = arabic.Trim();
        string trimmedEnglish = english.Trim();

        ArgumentOutOfRangeException.ThrowIfGreaterThan(trimmedArabic.Length, MaxLength);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(trimmedEnglish.Length, MaxLength);

        return new LocalizedName(trimmedArabic, trimmedEnglish);
    }

    protected override IEnumerable<object?> GetEqualityComponents()
    {
        yield return Arabic;
        yield return English;
    }
}
