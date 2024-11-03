<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtDevice extends Model
{
    use HasFactory;

    protected $table = 'vt_devices';

    const POSITION_TAGS = [
        'mounting_height' => [
            'ceiling_mounted',
            'wall_mounted',
            'pole_mounted',
            'corner_mounted',
            'roof_mounted',
            'low_level',
            'eye_level',
            'high_level',
        ],
        'directional' => [
            'facing_north',
            'facing_south',
            'facing_east',
            'facing_west',
            'facing_entrance',
            'facing_exit',
            'facing_street',
            'facing_building',
        ],
        'corner_position' => [
            'top_right_corner',
            'top_left_corner',
            'bottom_right_corner',
            'bottom_left_corner',
            'right_corner',
            'left_corner',
        ],
        'relative_position' => [
            'above_door',
            'beside_door',
            'after_entrance',
            'before_entrance',
            'over_counter',
            'over_register',
            'over_seating',
            'above_window',
            'beside_window',
        ],
        'coverage_area' => [
            'wide_angle',
            'narrow_angle',
            'panoramic',
            'overhead_view',
            'eye_level_view',
            'bird_eye_view',
            'diagonal_view',
        ],
        'location_context' => [
            'near_exit_sign',
            'near_fire_extinguisher',
            'near_emergency_light',
            'near_stairwell',
            'near_elevator',
            'near_bathroom',
            'near_kitchen',
            'near_register',
            'near_entrance',
        ],
        'mounting_type' => [
            'recessed_mount',
            'surface_mount',
            'pendant_mount',
            'bracket_mount',
            'pole_mount',
            'wall_arm_mount',
            'ceiling_grid_mount',
        ],
        'height_reference' => [
            'ground_plus_1m',
            'ground_plus_2m',
            'ground_plus_3m',
            'ground_plus_4m',
            'ground_plus_5m',
            'below_ceiling_1m',
            'below_ceiling_2m',
        ],
        'building_section' => [
            'front_section',
            'middle_section',
            'back_section',
            'east_wing',
            'west_wing',
            'north_wing',
            'south_wing',
            'main_building',
            'annex_building',
        ]
    ];

    const CAMERA_POSITIONS = [
        'entrance' => [
            'main_entrance',
            'back_entrance',
            'side_entrance',
            'emergency_exit',
            'delivery_entrance'
        ],
        'interior' => [
            'lobby',
            'reception',
            'hallway',
            'corridor',
            'elevator',
            'stairwell',
            'staircase'
        ],
        'rooms' => [
            'office',
            'meeting_room',
            'conference_room',
            'break_room',
            'cafeteria',
            'kitchen',
            'storage_room',
            'server_room',
            'utility_room'
        ],
        'parking' => [
            'parking_lot',
            'parking_entrance',
            'parking_exit',
            'loading_dock',
            'garage'
        ],
        'perimeter' => [
            'building_corner',
            'fence_line',
            'gate',
            'walkway',
            'outdoor_area'
        ],
        'floor_specific' => [
            'ground_floor',
            'basement',
            'mezzanine',
            'upper_floor'
        ]
    ];

    const CAMERA_BRANDS = [
        'hikvision' => 'Hikvision',
        'dahua' => 'Dahua',
        'axis' => 'Axis Communications',
        'hanwha' => 'Hanwha Techwin (Samsung)',
        'bosch' => 'Bosch Security',
        'uniview' => 'Uniview',
        'avigilon' => 'Avigilon',
        'pelco' => 'Pelco',
        'vivotek' => 'Vivotek',
        'panasonic' => 'Panasonic',
        'honeywell' => 'Honeywell',
        'cp_plus' => 'CP Plus',
        'tiandy' => 'Tiandy',
        'tvt' => 'TVT',
        'zkteco' => 'ZKTeco',
        'mobotix' => 'Mobotix',
        'reolink' => 'Reolink',
        'lorex' => 'Lorex',
        'swann' => 'Swann',
        'custom' => 'Custom/Other'
    ];

    // Flattened version for dropdowns
    const CAMERA_POSITIONS_FLAT = [
        // Entrances
        'main_entrance' => 'Main Entrance',
        'back_entrance' => 'Back Entrance',
        'side_entrance' => 'Side Entrance',
        'emergency_exit' => 'Emergency Exit',
        'delivery_entrance' => 'Delivery Entrance',
        // Interior
        'lobby' => 'Lobby',
        'reception' => 'Reception',
        'hallway' => 'Hallway',
        'corridor' => 'Corridor',
        'elevator' => 'Elevator',
        'stairwell' => 'Stairwell',
        'staircase' => 'Staircase',
        // Rooms
        'office' => 'Office',
        'meeting_room' => 'Meeting Room',
        'conference_room' => 'Conference Room',
        'break_room' => 'Break Room',
        'cafeteria' => 'Cafeteria',
        'kitchen' => 'Kitchen',
        'storage_room' => 'Storage Room',
        'server_room' => 'Server Room',
        'utility_room' => 'Utility Room',
        // Parking
        'parking_lot' => 'Parking Lot',
        'parking_entrance' => 'Parking Entrance',
        'parking_exit' => 'Parking Exit',
        'loading_dock' => 'Loading Dock',
        'garage' => 'Garage',
        // Perimeter
        'building_corner' => 'Building Corner',
        'fence_line' => 'Fence Line',
        'gate' => 'Gate',
        'walkway' => 'Walkway',
        'outdoor_area' => 'Outdoor Area',
        // Floor Specific
        'ground_floor' => 'Ground Floor',
        'basement' => 'Basement',
        'mezzanine' => 'Mezzanine',
        'upper_floor' => 'Upper Floor'
    ];

    protected $fillable = [
        'type',
        'device_id',
        'device_nickname',
        'location',
        'description',
        'camera_position',
        'tags',
        'brand',
        'custom_brand',
        'setup_status',
        'venue_id',
    ];


    protected $casts = [
        'type' => 'string',
        'brand' => 'string',
        'setup_status' => 'string',
        'tags' => 'array',
        'camera_position' => 'string',
    ];

    public function venue()
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function streams()
    {
        return $this->hasMany(VtStream::class, 'device_id');
    }

    public function isWithinSubscriptionLimit(): bool
    {
        $subscription = $this->venue->vtSubscription;
        if (!$subscription || $subscription->status !== 'active') {
            return false;
        }

        $currentCameraCount = $this->venue->devices()->count();
        return $currentCameraCount < $subscription->plan->max_cameras;
    }

    public function getCameraPositionDisplayAttribute(): ?string
    {
        return self::CAMERA_POSITIONS_FLAT[$this->camera_position] ?? null;
    }

    public function scopePosition($query, $position)
    {
        return $query->where('camera_position', $position);
    }

    public function scopeHasTag($query, $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public static function isValidCameraPosition($position): bool
    {
        return array_key_exists($position, self::CAMERA_POSITIONS_FLAT);
    }

    public static function getAllPositionTags(): array
    {
        return collect(self::POSITION_TAGS)
            ->flatten()
            ->values()
            ->toArray();
    }

    public static function getPositionTagsByCategory($category): array
    {
        return self::POSITION_TAGS[$category] ?? [];
    }

    public static function getCameraPositionsByCategory($category): array
    {
        return self::CAMERA_POSITIONS[$category] ?? [];
    }

    public static function isValidPositionTag($tag): bool
    {
        return in_array($tag, self::getAllPositionTags());
    }

    // Helper method for brand display
    public function getBrandDisplayAttribute(): string
    {
        if ($this->brand === 'custom') {
            return $this->custom_brand ?? 'Custom';
        }
        return self::CAMERA_BRANDS[$this->brand] ?? $this->brand;
    }
}
