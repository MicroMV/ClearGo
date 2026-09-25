-- CBE now follows the same sequence as the other colleges: Dean before Organization/YBC.
UPDATE requirements
SET status = 'INACTIVE', is_required = 0
WHERE requirement_code = 'CBE_YBC_APPROVAL';
