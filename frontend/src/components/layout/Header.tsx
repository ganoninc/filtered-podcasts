import { Link, NavLink, useLocation } from 'react-router-dom';
import { Icon } from '../ui/Icon';
import type { BootstrapData } from '../../lib/types';

interface HeaderProps {
  bootstrap: BootstrapData;
}

const navClass = ({ isActive }: { isActive: boolean }) => `nav-link ${isActive ? 'active' : ''}`;

export function Header({ bootstrap }: HeaderProps) {
  const location = useLocation();

  return (
    <header className="topbar">
      <div className="shell">
        <Link className="brand" to="/" aria-label="Filtered Podcasts home">
          <span className="brand-mark"><Icon name="filter" size={15} /></span>
          <span className="brand-name">Filtered Podcasts</span>
        </Link>
        <nav className="nav" aria-label="Main">
          <NavLink className={navClass} to="/" end>Feeds</NavLink>
          {bootstrap.user ? (
            <>
              <NavLink className={navClass} to="/admin/feeds">Manage</NavLink>
              <span className="nav-divider" aria-hidden="true" />
              <form method="post" action="/logout">
                <input type="hidden" name="_csrf_token" value={bootstrap.csrf.logout} />
                <button className="btn btn-ghost btn-sm" type="submit" title={bootstrap.user.email}>Sign out</button>
              </form>
            </>
          ) : location.pathname !== '/login' ? (
            <NavLink className={navClass} to="/login">Sign in</NavLink>
          ) : null}
        </nav>
      </div>
    </header>
  );
}
