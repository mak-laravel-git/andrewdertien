import { Link, router, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { ArrowRight, Bell, Compass, CreditCard, Gem, Globe, Heart, HandCoins, LayoutDashboard, LogIn, KeyRound, LogOut, Menu, MessageCircle, Search, Settings, Sparkles, UserRound, Users, Wallet, Waves, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { toast } from 'sonner';
import { PaymentModeBadge } from '@/components/pond/payment-mode';
import { logout } from '@/routes';

export function PondMark({ small = false }: { small?: boolean }) {
    return <span className={`pond-mark ${small ? 'pond-mark-small' : ''}`} aria-hidden="true"><img src="/askupon/lotus.svg" alt="" /></span>;
}
export function Brand({ dark = false }: { dark?: boolean }) { return <Link href="/" className={`pond-brand ${dark ? 'pond-brand-light' : ''}`}><PondMark /><span className="pond-brand-text"><span className="pond-brand-ask">Ask Upon </span><em>A Pond</em><sup className="pond-brand-tm">™</sup></span></Link>; }
export function Avatar({ name, hue = 'lilac', size = '', src }: { name: string; hue?: string; size?: string; src?: string | null }) { return <span aria-label={name} className={`pond-avatar avatar-${hue} ${size}`} title={name}>{src ? <img src={src} alt="" className="pond-avatar-img" /> : name.split(' ').map(a => a[0]).slice(0, 2).join('').toUpperCase()}</span>; }
/** A stable avatar colour per member so the same person always looks the same. */
export function hueFor(seed: number): string { const hues = ['lilac', 'sky', 'peach', 'mint', 'pink', 'blue']; return hues[Math.abs(seed) % hues.length]; }
export function CategoryPill({ category }: { category: string }) { return <span className={`pond-tag tag-${category.toLowerCase()}`}>{category}</span>; }
export function Progress({ value, max, tone = 'purple' }: { value: number; max: number; tone?: string }) { return <div className="pond-progress" role="progressbar" aria-valuemin={0} aria-valuemax={max} aria-valuenow={value}><div className={`pond-progress-inner progress-${tone}`} style={{ width: `${Math.max(0, Math.min(100, max ? Math.round(value / max * 100) : 0))}%` }} /></div>; }
export function DemoBanner() { return <div className="pond-demo-banner"><span className="demo-pulse" />Interactive design preview · Sample profiles and activity only · No real transfers</div>; }
export function SectionEyebrow({ children }: { children: ReactNode }) { return <span className="pond-eyebrow"><Sparkles size={13} />{children}</span>; }
export function EmptyState({ title, body, icon }: { title: string; body: string; icon: ReactNode }) { return <div className="pond-empty"><div className="pond-empty-icon">{icon}</div><h3>{title}</h3><p>{body}</p></div>; }
export function Modal({ title, children, onClose, subtitle }: { title: string; subtitle?: string; children: ReactNode; onClose: () => void }) {
    useEffect(() => { function down(e: KeyboardEvent) { if (e.key === 'Escape') onClose(); } document.addEventListener('keydown', down); return () => document.removeEventListener('keydown', down); }, [onClose]);
    return <div className="pond-modal-backdrop" onMouseDown={e => { if (e.target === e.currentTarget) onClose(); }} role="presentation"><div className="pond-modal" role="dialog" aria-modal="true" aria-label={title}><button className="pond-icon-btn pond-modal-close" onClick={onClose} aria-label="Close dialog"><X size={18} /></button><h2>{title}</h2>{subtitle && <p className="pond-muted">{subtitle}</p>}{children}</div></div>;
}
type ShellUser = { id: number; name: string; avatar?: string | null; is_admin?: boolean };
type ShellLink = { label: string; href: string; icon: LucideIcon };

const adminLinks: ShellLink[] = [
    { label: 'Overview', href: '/admin/dashboard', icon: LayoutDashboard },
    { label: 'Balls', href: '/admin/ponds', icon: Waves },
    { label: 'Purchases & payments', href: '/admin/payments', icon: CreditCard },
    { label: 'Withdrawals', href: '/admin/withdrawals', icon: HandCoins },
    { label: 'Wallets', href: '/admin/wallets', icon: Wallet },
    { label: 'Payment settings', href: '/admin/settings/stripe', icon: KeyRound },
    { label: 'Members', href: '/admin/users', icon: Users },
];

const memberLinks: ShellLink[] = [
    { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
    { label: 'The Balls', href: '/dashboard/ponds', icon: Sparkles },
    { label: 'My Balls', href: '/dashboard/my-ponds', icon: Gem },
    { label: 'Payment history', href: '/dashboard/payments', icon: CreditCard },
    { label: 'Wallet', href: '/dashboard/wallet', icon: Wallet },
    { label: 'Explore the Pond', href: '/explore', icon: Compass },
    { label: 'The Pond', href: '/community', icon: Waves },
    { label: 'Messages', href: '/messages', icon: MessageCircle },
    { label: 'My Profile', href: '/my-profile', icon: UserRound },
    { label: 'Connections', href: '/preview/connections', icon: Heart },
];

const guestLinks: ShellLink[] = [
    { label: 'Overview', href: '/login', icon: LayoutDashboard },
    { label: 'The Balls', href: '/ponds', icon: Waves },
];

/** The longest matching link wins, so /dashboard is not active on /dashboard/ponds. */
function activeHref(links: ShellLink[], path: string): string | undefined {
    return links
        .filter(({ href }) => path === href || path.startsWith(`${href}/`))
        .sort((a, b) => b.href.length - a.href.length)[0]?.href;
}

export function PondShell({ children, page = 'The Pond', subheading, action, demo = true, hideHeading = false }: { children: ReactNode; page?: string; subheading?: string; action?: ReactNode; demo?: boolean; hideHeading?: boolean }) {
    useEffect(() => { function key(e: KeyboardEvent) { if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); setShowSearch(true); } } window.addEventListener('keydown', key); return () => window.removeEventListener('keydown', key); }, []);
    const [menu, setMenu] = useState(false); const [search, setSearch] = useState(''); const [showSearch, setShowSearch] = useState(false); const [notifications, setNotifications] = useState(false);
    const shared = usePage().props as { auth?: { user?: ShellUser | null }; unreadMessages?: number; notifications?: { unread: number; items: { id: string; message: string; url: string | null; read: boolean; time: string | null }[] } | null };
    const alerts = shared.notifications ?? { unread: 0, items: [] };
    const user = shared.auth?.user ?? null;
    const unreadMessages = user ? (shared.unreadMessages ?? 0) : 0;
    const isAdmin = user?.is_admin === true;
    const avatarSrc = user?.avatar ? (user.avatar.startsWith('http') ? user.avatar : `/storage/${user.avatar}`) : null;
    const profileHref = user ? '/my-profile' : '/preview/profile';
    const links = isAdmin ? adminLinks : user ? memberLinks : guestLinks;
    const current = activeHref(links, typeof window !== 'undefined' ? window.location.pathname : '');

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        setShowSearch(false);
        router.get('/explore', search.trim() ? { search: search.trim() } : {});
    };

    return <div className="pond-app"><aside className={`pond-sidebar ${menu ? 'is-open' : ''}`}><div className="pond-sidebar-brand"><Brand /><button className="pond-icon-btn pond-mobile-close" onClick={() => setMenu(false)} aria-label="Close menu"><X size={20} /></button></div><div className="pond-sidebar-little">{isAdmin ? 'ADMINISTRATION' : 'YOUR COMMUNITY'}</div><nav className="pond-side-links" aria-label={isAdmin ? 'Admin navigation' : 'Member navigation'}>{links.map(({ label, href, icon: Icon }) => <Link onClick={() => setMenu(false)} key={label} href={href} className={`pond-side-link ${current === href ? 'active' : ''}`}><Icon size={19} /><span>{label}</span>{href === '/messages' && unreadMessages > 0 && <span className="ms-nav-badge" aria-label={`${unreadMessages} unread messages`}>{unreadMessages}</span>}</Link>)}</nav><div className="pond-sidebar-bottom">{!isAdmin && <div className="pond-side-quote"><div className="pond-side-quote-star"><Sparkles size={18} /></div><strong>Keep the balls rolling.</strong><p>Give a ball. Get a ball. Pass one along. See what happens.</p><Link href="/dashboard/ponds">Explore the Balls <ArrowRight size={13} /></Link></div>}<Link href="/" className="pond-side-link"><Globe size={19} /> View website</Link>{user && <Link href="/my-account" className="pond-side-link"><Settings size={19} /> Account settings</Link>}{user ? <Link href={logout()} as="button" onClick={() => router.flushAll()} className="pond-side-link pond-side-logout" data-test="logout-button"><LogOut size={19} /> Log out</Link> : <Link href="/login" className="pond-side-link"><LogIn size={19} /> Sign in</Link>}</div></aside>{menu && <button className="pond-mobile-shade" onClick={() => setMenu(false)} aria-label="Close navigation" />}
        <div className="pond-main"><header className="pond-topbar"><div className="pond-top-left"><button className="pond-icon-btn pond-menu-toggle" aria-label="Open menu" onClick={() => setMenu(true)}><Menu size={21} /></button><div className="pond-breadcrumb"><span>{isAdmin ? 'Admin' : 'Community'}</span><span className="crumb-sep">/</span><strong>{page}</strong></div></div><div className="pond-header-actions"><PaymentModeBadge /><button className="pond-search-trigger" onClick={() => setShowSearch(true)}><Search size={17} /><span>Search the pond...</span><kbd>⌘ K</kbd></button><div className="pond-notification-wrap"><button className="pond-icon-btn pond-bell" onClick={() => setNotifications(v => !v)} aria-label="Notifications"><Bell size={19} />{unreadMessages + alerts.unread > 0 && <span className="ms-bell-dot" />}</button>{notifications && <div className="pond-popover" data-test="notification-popover"><strong>Notifications</strong>{unreadMessages > 0 && <p><Link href="/messages" onClick={() => setNotifications(false)}>You have {unreadMessages} unread {unreadMessages === 1 ? 'message' : 'messages'}</Link></p>}{alerts.items.map(item => <p key={item.id} className={`ms-alert ${item.read ? '' : 'is-unread'}`}>{item.url ? <Link href={item.url} onClick={() => setNotifications(false)}>{item.message}</Link> : item.message}<small>{item.time}</small></p>)}{unreadMessages === 0 && alerts.items.length === 0 && <p>You&rsquo;re all caught up.</p>}{alerts.unread > 0 && <button type="button" className="ms-mark-read" onClick={() => router.post('/notifications/read', {}, { preserveScroll: true })}>Mark all as read</button>}</div>}</div><Link href={profileHref} aria-label="Your profile"><Avatar name={user?.name ?? 'Jordan Lee'} hue={user ? hueFor(user.id) : 'lilac'} size="avatar-small" src={avatarSrc} /></Link></div></header>{demo && <DemoBanner />}<main className="pond-content">{!hideHeading && <div className="pond-page-heading"><div><span className="pond-page-kicker">ASK UPON A POND</span><h1>{page}</h1>{subheading && <p>{subheading}</p>}</div>{action}</div>}{children}</main><footer className="pond-app-footer"><span>© 2026 Ask Upon A Pond · There's room for everyone</span><span>{demo ? 'Design preview · Laravel + Inertia + React' : 'Ask Upon A Pond community'}</span></footer></div>
        {showSearch && <Modal title="Find your people" subtitle="Search members by name, username or city." onClose={() => setShowSearch(false)}><form onSubmit={submitSearch}><div className="pond-search-field"><Search size={19} /><input autoFocus value={search} onChange={e => setSearch(e.target.value)} placeholder="Search the community..." aria-label="Search members" maxLength={100} /></div><div className="pond-search-results"><button type="submit" className="pond-search-submit"><Search size={15} />{search.trim() ? `Search members for “${search.trim()}”` : 'Browse all members'}<ArrowRight size={14} /></button></div></form></Modal>}</div>;
}
export function GiveModal({ onClose, initial = 10, member = 'a community member' }: { onClose: () => void; initial?: number; member?: string }) { const [selected, setSelected] = useState(initial); return <Modal title="Toss a ball in the Pond" subtitle={`Choose a ball for ${member}.`} onClose={onClose}><div className="pond-give-grid">{[2, 5, 10, 20, 50, 100].map(n => <button key={n} className={`pond-give-choice ${selected === n ? 'selected' : ''}`} onClick={() => setSelected(n)}><img src={`/quantum/images/ball-${n}.png`} alt="" /><strong>${n.toLocaleString()}</strong></button>)}</div><div className="pond-form-note">This is a visual preview. Purchases, card processing and transfers are not connected.</div><button className="pond-btn pond-btn-primary pond-wide" onClick={() => toast.info('Design preview: no charge or gift was made.')}><Sparkles size={17} /> Preview ${selected} Ball <ArrowRight size={16} /></button></Modal>; }
export function CopyShare({ title = 'Ask Upon A Pond' }: { title?: string }) { const copy = () => { const url = typeof window !== 'undefined' ? window.location.href : 'Ask Upon A Pond'; if (navigator.clipboard?.writeText) { navigator.clipboard.writeText(url).then(() => toast.success('Preview link copied')).catch(() => toast.info('Share the Ask Upon A Pond preview')); } else { toast.info(`Share ${title}`); } }; return <button onClick={copy} className="pond-text-btn">Share</button>; }
